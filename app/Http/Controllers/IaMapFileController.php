<?php

namespace App\Http\Controllers;

use App\Models\IaMapFeature;
use App\Models\IaMapLot;
use App\Models\IaProfile;
use App\Support\IaMapImport;
use App\Support\IaProfileAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Upload / replace / remove the service-area map of one IA (KMZ, KML or GeoJSON, WGS84).
 * Needs ia.edit. The profile page calls this with fetch(): first with preview=1 (nothing is saved, the
 * summary is shown in a SweetAlert), then again without it to save.
 */
class IaMapFileController extends Controller
{
    public function store(Request $request, IaProfile $iaProfile): JsonResponse
    {
        abort_unless($request->user()->hasPermission('ia.edit'), 403);

        if ($iaProfile->trashed()) {
            throw ValidationException::withMessages(['file' => __('This IA profile is archived. Restore it before changing its map.')]);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:12288'],
            'mode' => ['nullable', Rule::in(['replace', 'add'])],
            'lot_status' => ['nullable', Rule::in(array_keys(IaMapLot::STATUSES))],
        ], [
            'file.required' => __('Choose a .kmz, .kml or .geojson file.'),
            'file.uploaded' => __('The file could not be uploaded. It may be larger than the server allows (upload_max_filesize / post_max_size in php.ini).'),
            'file.max' => __('The file is too large (maximum 12 MB).'),
        ]);

        $file = $request->file('file');

        try {
            $result = IaMapImport::fromFile($file->getRealPath(), $file->getClientOriginalName());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        // The same shapes must not sit on two IAs (the map would draw them on top of each other)
        if ($other = $this->alreadyOnAnotherIa($iaProfile, $result)) {
            throw ValidationException::withMessages(['file' => __('This map is already on :name (:n of :total shapes are identical). Each IA needs its own file. If it was uploaded to the wrong IA, remove the map there first, then upload it here.', [
                'name' => $other['name'], 'n' => $other['matches'], 'total' => $other['total'],
            ])]);
        }

        $existing = IaMapLot::where('ia_profile_id', $iaProfile->id)->count();
        $existingNet = IaMapFeature::where('ia_profile_id', $iaProfile->id)->count();
        $summary = [
            'lots' => count($result['lots']),
            'total_ha' => $result['total_ha'],
            'features' => count($result['features']),
            'counts' => (object) $result['counts'],
            'canal_km' => $result['canal_km'],
            'warnings' => $result['warnings'],
            'existing_lots' => $existing,
            'existing_features' => $existingNet,
            'service_area_ha' => $iaProfile->service_area_ha !== null ? (float) $iaProfile->service_area_ha : null,
        ];

        if ($request->boolean('preview')) {
            return response()->json(['preview' => true] + $summary);
        }

        $mode = $request->input('mode', 'replace');   // applies to lots; the canal network in a file always replaces the old network
        $status = $request->input('lot_status', 'op');
        $source = mb_substr('Uploaded: '.$file->getClientOriginalName().' ('.now()->format('Y-m-d').')', 0, 120);
        $now = now();

        DB::transaction(function () use ($iaProfile, $result, $mode, $status, $source, $now) {
            if ($result['lots'] !== []) {
                if ($mode === 'replace') {
                    IaMapLot::where('ia_profile_id', $iaProfile->id)->delete();
                }

                foreach (array_chunk($result['lots'], 100) as $chunk) {
                    IaMapLot::insert(array_map(fn ($lot) => [
                        'ia_profile_id' => $iaProfile->id,
                        'lot_name' => $lot['name'],
                        'area_ha' => $lot['area_ha'],
                        'lot_status' => $status,
                        'geometry' => json_encode($lot['geometry'], JSON_UNESCAPED_SLASHES),
                        'source' => $source,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $chunk));
                }
            }

            if ($result['features'] !== []) {
                IaMapFeature::where('ia_profile_id', $iaProfile->id)->delete();

                foreach (array_chunk($result['features'], 100) as $chunk) {
                    IaMapFeature::insert(array_map(fn ($f) => [
                        'ia_profile_id' => $iaProfile->id,
                        'kind' => $f['kind'],
                        'name' => $f['name'],
                        'group_name' => $f['group'],
                        'length_m' => $f['length_m'],
                        'geometry' => json_encode($f['geometry'], JSON_UNESCAPED_SLASHES),
                        'source' => $source,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $chunk));
                }
            }
        });

        IaProfileAudit::mapUploaded($iaProfile, $summary['lots'], $summary['total_ha'], $mode, $file->getClientOriginalName(), $summary['features'], $summary['canal_km']);

        $parts = [];
        if ($summary['lots'] > 0) {
            $parts[] = __(':n lots (:ha ha)', ['n' => $summary['lots'], 'ha' => number_format($summary['total_ha'], 2)]);
        }
        if ($summary['features'] > 0) {
            $parts[] = __('canal network (:n items, :km km of canal)', ['n' => $summary['features'], 'km' => number_format($summary['canal_km'], 2)]);
        }
        $message = __('Saved to the map of :name: :what.', ['name' => $iaProfile->ia_name, 'what' => implode(' + ', $parts)]);
        $request->session()->flash('status', $message);

        return response()->json(['message' => $message] + $summary);
    }

    /**
     * Compare the upload with the lots and canal items of every other IA. Two shapes are "identical" when their
     * first point / centre agree to about 1 metre. Returns the IA with most matches (at least half of the upload) or null.
     *
     * @return array{name: string, matches: int, total: int}|null
     */
    private function alreadyOnAnotherIa(IaProfile $profile, array $result): ?array
    {
        $mine = [];

        foreach ($result['lots'] as $lot) {
            $mine['L'.self::key($lot['geometry'])] = true;
        }

        foreach ($result['features'] as $f) {
            $mine['F'.$f['kind'].self::key($f['geometry'])] = true;
        }

        if ($mine === []) {
            return null;
        }

        $hits = [];

        $count = function (string $prefix, $rows) use (&$hits, $mine) {
            foreach ($rows as $row) {
                $geometry = json_decode($row->geometry, true);

                if (is_array($geometry) && isset($mine[$prefix.($row->kind ?? '').self::key($geometry)])) {
                    $hits[$row->ia_profile_id] = ($hits[$row->ia_profile_id] ?? 0) + 1;
                }
            }
        };

        $count('L', IaMapLot::withoutGlobalScopes()->where('ia_profile_id', '!=', $profile->id)->select(['ia_profile_id', 'geometry'])->cursor());
        $count('F', IaMapFeature::withoutGlobalScopes()->where('ia_profile_id', '!=', $profile->id)->select(['ia_profile_id', 'kind', 'geometry'])->cursor());

        if ($hits === []) {
            return null;
        }

        arsort($hits);
        $id = array_key_first($hits);

        if ($hits[$id] < max(1, (int) ceil(count($mine) / 2))) {
            return null;
        }

        $other = IaProfile::withTrashed()->withoutGlobalScopes()->find($id);

        return ['name' => $other?->ia_name ?? __('another IA'), 'matches' => min($hits[$id], count($mine)), 'total' => count($mine)];
    }

    /** Rounded first point of a shape (polygon, line or point), as a text key. */
    private static function key(array $geometry): string
    {
        $c = $geometry['coordinates'] ?? [];

        while (is_array($c) && isset($c[0]) && is_array($c[0])) {
            $c = $c[0];
        }

        return is_array($c) && isset($c[1]) ? round((float) $c[0], 5).','.round((float) $c[1], 5) : '';
    }

    public function destroy(Request $request, IaProfile $iaProfile): JsonResponse
    {
        abort_unless($request->user()->hasPermission('ia.edit'), 403);
        abort_if($iaProfile->trashed(), 403, __('This IA profile is archived. Restore it before changing its map.'));

        $count = IaMapLot::where('ia_profile_id', $iaProfile->id)->count();
        $net = IaMapFeature::where('ia_profile_id', $iaProfile->id)->count();

        if ($count + $net === 0) {
            return response()->json(['message' => __('This IA has no map to remove.')], 404);
        }

        // ?part=network removes only the canal network; otherwise lots and network
        $only = $request->input('part');
        if ($only !== 'network') {
            IaMapLot::where('ia_profile_id', $iaProfile->id)->delete();
        } else {
            $count = 0;
        }
        IaMapFeature::where('ia_profile_id', $iaProfile->id)->delete();
        IaProfileAudit::mapRemoved($iaProfile, $count, $net);

        $message = __('The map of :name was removed (:n lots, :f network items).', ['name' => $iaProfile->ia_name, 'n' => $count, 'f' => $net]);
        $request->session()->flash('status', $message);

        return response()->json(['message' => $message]);
    }
}
