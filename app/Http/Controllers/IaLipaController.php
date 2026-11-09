<?php

namespace App\Http\Controllers;

use App\Models\IaLipaEntry;
use App\Models\IaProfile;
use App\Support\IaLipaRules;
use App\Support\IaProfileAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * LIPA (List of Irrigated and Planted Area) records of one IA profile.
 *   view   -> ia.view      add / edit / delete -> ia.edit  (and the profile must not be archived)
 * The office rule is inherited from the profile: if the profile is not visible to you, neither are its LIPA lines.
 */
class IaLipaController extends Controller
{
    public function index(Request $request, IaProfile $iaProfile): View
    {
        $this->requirePermission('ia.view');

        $lines = $iaProfile->lipaEntries();

        return view('ia-profiles.lipa', [
            'profile' => $iaProfile->loadMissing(['office:id,code,name', 'province:id,name']),
            'canEdit' => $this->canWrite($request, $iaProfile),
            'years' => (clone $lines)->distinct()->orderByDesc('crop_year')->pluck('crop_year'),
            'sectors' => (clone $lines)->distinct()->pluck('sector')->sort(SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'seasons' => IaLipaEntry::SEASONS,
            'selectedYear' => $request->integer('year') ?: null,
            'selectedSeason' => array_key_exists((string) $request->query('season'), IaLipaEntry::SEASONS) ? $request->query('season') : null,
        ]);
    }

    /** JSON feed for the DataTable (optional ?year= &season= &sector=). */
    public function data(Request $request, IaProfile $iaProfile): JsonResponse
    {
        $this->requirePermission('ia.view');
        $canEdit = $this->canWrite($request, $iaProfile);

        $rows = $iaProfile->lipaEntries()
            ->when($request->filled('year'), fn ($q) => $q->where('crop_year', (int) $request->query('year')))
            ->when($request->filled('season'), fn ($q) => $q->where('season', $request->query('season')))
            ->when($request->filled('sector'), fn ($q) => $q->where('sector', $request->query('sector')))
            ->orderByDesc('crop_year')->orderBy('season')->orderBy('id')
            ->get()
            ->map(fn (IaLipaEntry $e) => [
                'id' => $e->id,
                'crop_year' => $e->crop_year,
                'season' => $e->season,
                'season_label' => IaLipaEntry::SEASONS[$e->season] ?? $e->season,
                'sector' => $e->sector,
                'sector_no' => preg_match('/\d+/', (string) $e->sector, $m) ? (int) $m[0] : 0,
                'no' => $e->no,
                'lot_no' => $e->lot_no,
                'landowner' => $e->landownerName(),
                'landowner_first_name' => $e->landowner_first_name,
                'landowner_mi' => $e->landowner_mi,
                'landowner_last_name' => $e->landowner_last_name,
                'service_area_ha' => (float) $e->service_area_ha,
                'irrigated_planted_area_ha' => (float) $e->irrigated_planted_area_ha,
                'variety_inbred' => $e->variety_inbred,
                'variety_hybrid' => $e->variety_hybrid,
                'date_sown' => $e->date_sown?->format('Y-m-d'),
                'date_planted' => $e->date_planted?->format('Y-m-d'),
                'expected_harvest_date' => $e->expected_harvest_date?->format('Y-m-d'),
                'rsbsa_reg_no' => $e->rsbsa_reg_no,
                'crop_insurance' => $e->crop_insurance,
                'remarks' => $e->remarks,
                'source' => $e->source,
                'can_edit' => $canEdit,
                'update_url' => route('ia-profiles.lipa.update', [$iaProfile, $e]),
                'delete_url' => route('ia-profiles.lipa.destroy', [$iaProfile, $e]),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request, IaProfile $iaProfile): JsonResponse
    {
        $this->requirePermission('ia.edit');

        $data = IaLipaRules::validate($request->all(), $iaProfile);

        $entry = new IaLipaEntry($data);
        $entry->forceFill([
            'ia_profile_id' => $iaProfile->id,
            'source' => 'manual',
            'created_by' => $request->user()->id,
        ])->save();

        IaProfileAudit::lipaCreated($iaProfile, $entry);

        return response()->json(['message' => __('LIPA record added.')], 201);
    }

    public function update(Request $request, IaProfile $iaProfile, IaLipaEntry $entry): JsonResponse
    {
        $this->requirePermission('ia.edit');
        $this->ensureBelongs($iaProfile, $entry);

        $data = IaLipaRules::validate($request->all(), $iaProfile, $entry);

        $entry->fill($data);
        $changes = IaProfileAudit::lipaChanges($entry);

        if ($changes === []) {
            return response()->json(['message' => __('No changes were made.'), 'unchanged' => true]);
        }

        $entry->save();
        IaProfileAudit::lipaUpdated($iaProfile, $entry, $changes);

        return response()->json(['message' => __('LIPA record updated.')]);
    }

    /** LIPA lines have no archive: deleting removes the row, and the profile history keeps a line about it. */
    public function destroy(Request $request, IaProfile $iaProfile, IaLipaEntry $entry): JsonResponse
    {
        $this->requirePermission('ia.edit');
        $this->ensureBelongs($iaProfile, $entry);

        $entry->delete();
        IaProfileAudit::lipaDeleted($iaProfile, $entry);

        return response()->json(['message' => __('LIPA record deleted.')]);
    }

    /* ------------------------------------------------------------------ */

    private function canWrite(Request $request, IaProfile $profile): bool
    {
        return $request->user()->hasPermission('ia.edit') && ! $profile->trashed();
    }

    private function ensureBelongs(IaProfile $profile, IaLipaEntry $entry): void
    {
        abort_unless((int) $entry->ia_profile_id === (int) $profile->id, 404);
    }

    private function requirePermission(string $code): void
    {
        abort_unless(auth()->user()?->hasPermission($code), 403);
    }
}
