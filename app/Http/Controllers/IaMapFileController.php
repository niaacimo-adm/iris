<?php

namespace App\Http\Controllers;

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

        $existing = IaMapLot::where('ia_profile_id', $iaProfile->id)->count();
        $summary = [
            'lots' => count($result['lots']),
            'total_ha' => $result['total_ha'],
            'warnings' => $result['warnings'],
            'existing_lots' => $existing,
            'service_area_ha' => $iaProfile->service_area_ha !== null ? (float) $iaProfile->service_area_ha : null,
        ];

        if ($request->boolean('preview')) {
            return response()->json(['preview' => true] + $summary);
        }

        $mode = $request->input('mode', 'replace');
        $status = $request->input('lot_status', 'op');
        $source = mb_substr('Uploaded: '.$file->getClientOriginalName().' ('.now()->format('Y-m-d').')', 0, 120);
        $now = now();

        DB::transaction(function () use ($iaProfile, $result, $mode, $status, $source, $now) {
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
        });

        IaProfileAudit::mapUploaded($iaProfile, $summary['lots'], $summary['total_ha'], $mode, $file->getClientOriginalName());

        $message = __(':n lots (:ha ha) were saved to the map of :name.', [
            'n' => $summary['lots'], 'ha' => number_format($summary['total_ha'], 2), 'name' => $iaProfile->ia_name,
        ]);
        $request->session()->flash('status', $message);

        return response()->json(['message' => $message] + $summary);
    }

    public function destroy(Request $request, IaProfile $iaProfile): JsonResponse
    {
        abort_unless($request->user()->hasPermission('ia.edit'), 403);
        abort_if($iaProfile->trashed(), 403, __('This IA profile is archived. Restore it before changing its map.'));

        $count = IaMapLot::where('ia_profile_id', $iaProfile->id)->count();

        if ($count === 0) {
            return response()->json(['message' => __('This IA has no map to remove.')], 404);
        }

        IaMapLot::where('ia_profile_id', $iaProfile->id)->delete();
        IaProfileAudit::mapRemoved($iaProfile, $count);

        $message = __('The map of :name was removed (:n lots).', ['name' => $iaProfile->ia_name, 'n' => $count]);
        $request->session()->flash('status', $message);

        return response()->json(['message' => $message]);
    }
}
