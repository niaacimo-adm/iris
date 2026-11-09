<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\IaDeletionRequest;
use App\Models\IaLipaEntry;
use App\Models\IaProfile;
use App\Support\IaAccess;
use App\Support\IaProfileAudit;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard. Every number comes through the IaProfile office scope, so an IMO user sees only their own office
 * and Super Admin / Regional Staff see all offices.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $me = $request->user();

        if (! $me->hasPermission('ia.view')) {
            return view('dashboard', ['canSeeIa' => false]);
        }

        $regionWide = IaAccess::regionWide($me);
        $has = fn (string $col) => "SUM(CASE WHEN {$col} IS NOT NULL AND {$col} <> '' THEN 1 ELSE 0 END)";

        // --- headline numbers
        $totals = IaProfile::query()->selectRaw(
            "COUNT(*) AS total,
             SUM(status = 'operational') AS operational,
             SUM(status = 'non-operational') AS non_operational,
             COALESCE(SUM(service_area_ha), 0) AS service_area,
             COALESCE(SUM(actual_ia_members), 0) AS members,
             {$has('cis_name')} AS cis, {$has('nis')} AS nis, {$has('spip_name')} AS spip"
        )->first();

        // --- IAs per province (and per office for region-wide users)
        $byProvince = IaProfile::query()
            ->join('provinces', 'provinces.id', '=', 'ia_profiles.province_id')
            ->selectRaw('provinces.name AS label, COUNT(*) AS total')
            ->groupBy('provinces.name')->orderByDesc('total')->get();

        $byOffice = $regionWide
            ? IaProfile::query()
                ->join('offices', 'offices.id', '=', 'ia_profiles.office_id')
                ->selectRaw('offices.code AS label, COUNT(*) AS total')
                ->groupBy('offices.code')->orderByDesc('total')->get()
            : collect();

        // --- LIPA per crop year + season (active profiles only)
        $lipaPeriods = IaLipaEntry::query()
            ->whereIn('ia_profile_id', IaProfile::query()->select('id'))
            ->selectRaw('crop_year, season, COUNT(*) AS lots, COUNT(DISTINCT ia_profile_id) AS ias,
                         SUM(service_area_ha) AS service_area, SUM(irrigated_planted_area_ha) AS planted_area')
            ->groupBy('crop_year', 'season')
            ->orderByDesc('crop_year')->orderBy('season')
            ->limit(4)->get();

        // --- things that need attention
        $noLipa = IaProfile::where('status', 'operational')->whereDoesntHave('lipaEntries')->count();
        $noOfficers = IaProfile::whereDoesntHave('officers')->count();
        $noPresident = IaProfile::where(fn ($q) => $q->whereNull('president_name')->orWhere('president_name', ''))->count();
        $noContact = IaProfile::where(fn ($q) => $q->whereNull('contact_number')->orWhere('contact_number', ''))->count();

        // --- delete requests: approvers see what is waiting, others see how many of theirs are pending
        $isApprover = IaAccess::canApproveDeletion($me);
        $pendingRequests = $isApprover
            ? IaDeletionRequest::with(['profile:id,ia_name', 'requester:id,first_name,last_name'])
                ->where('status', 'pending')->latest('id')->limit(5)->get()
            : collect();
        $pendingCount = IaDeletionRequest::where('status', 'pending')
            ->when(! $isApprover, fn ($q) => $q->where('requested_by', $me->id))->count();

        // --- recent activity on IA profiles
        $activity = AuditLog::query()
            ->with('user:id,first_name,last_name')
            ->where('entity_type', IaProfileAudit::ENTITY)
            ->when(! $regionWide, fn ($q) => $q->where('office_id', $me->office_id))
            ->latest('id')->limit(8)->get();

        return view('dashboard', [
            'canSeeIa' => true,
            'regionWide' => $regionWide,
            'scopeLabel' => $regionWide ? __('All offices') : (\App\Models\Office::find($me->office_id)?->code ?? __('Your office')),
            'totals' => $totals,
            'byProvince' => $byProvince,
            'byOffice' => $byOffice,
            'lipaPeriods' => $lipaPeriods,
            'attention' => [
                ['label' => __('Operational IAs with no LIPA record'), 'value' => $noLipa],
                ['label' => __('IAs with no officers listed'), 'value' => $noOfficers],
                ['label' => __('IAs with no president'), 'value' => $noPresident],
                ['label' => __('IAs with no contact number'), 'value' => $noContact],
            ],
            'isApprover' => $isApprover,
            'pendingRequests' => $pendingRequests,
            'pendingCount' => $pendingCount,
            'archivedCount' => IaProfile::onlyTrashed()->count(),
            'activity' => $activity,
        ]);
    }
}
