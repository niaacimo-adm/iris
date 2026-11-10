<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CongressionalDistrict;
use App\Models\IaDeletionRequest;
use App\Models\IaProfile;
use App\Models\Scopes\OfficeScope;
use App\Models\Province;
use App\Models\User;
use App\Support\IaAccess;
use App\Support\IaProfileAudit;
use App\Support\IaProfileRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IaProfileController extends Controller
{
    public function index(Request $request): View
    {
        $this->requirePermission('ia.view');
        $me = $request->user();
        $geo = $this->geo($me);

        return view('ia-profiles.index', [
            'showOffice' => IaAccess::regionWide($me),
            'statuses'   => IaProfile::STATUSES,
            'provinces'  => collect($geo['provinces'])->values()->sort()->values(),
            'offices'    => IaAccess::allowedOffices($me),
            'archivedCount' => IaProfile::onlyTrashed()->count(),
        ]);
    }

    /** JSON feed for the DataTable. The office scope on the model already limits the rows. */
    public function data(Request $request): JsonResponse
    {
        $this->requirePermission('ia.view');
        $me = $request->user();
        $canEdit = $me->hasPermission('ia.edit');
        $canDelete = $me->hasPermission('ia.delete');
        // Super Admin / Regional Staff delete (archive) directly; everyone else sends a request for approval.
        $deleteMode = IaAccess::canApproveDeletion($me) ? 'direct' : 'request';
        $pendingIds = IaDeletionRequest::where('status', 'pending')->pluck('ia_profile_id')->flip();

        $rows = IaProfile::query()
            ->with(['office:id,code', 'province:id,name', 'district:id,name', 'assignedUser:id,first_name,last_name'])
            ->withCount('lipaEntries')
            ->orderBy('ia_name')
            ->get()
            ->map(fn (IaProfile $p) => [
                'id'             => $p->id,
                'ia_name'        => $p->ia_name,
                'ia_code'        => $p->ia_code,
                'cis_name'       => $p->cis_name,
                'systems'        => $p->systems(),
                'president_name' => $p->president_name,
                'contact_number' => $p->contact_number,
                'service_area'   => $p->service_area_ha !== null ? (float) $p->service_area_ha : null,
                'members'        => $p->actual_ia_members,
                'tsags'          => $p->tsags_count,
                'status'         => $p->status,
                'status_label'   => IaProfile::STATUSES[$p->status] ?? $p->status,
                'assigned'       => $p->assignedUser?->name,
                'province'       => $p->province?->name,
                'district'       => $p->district?->name,
                'office'         => $p->office?->code,
                'can_edit'       => $canEdit,
                'can_delete'     => $canDelete,
                'show_url'       => route('ia-profiles.show', $p),
                'edit_url'       => route('ia-profiles.edit', $p),
                'delete_url'     => route('ia-profiles.destroy', $p),
                'delete_mode'    => $deleteMode,
                'pending_request' => $pendingIds->has($p->id),
                'request_url'    => route('ia-profiles.deletion-requests.store', $p),
                'requests_url'   => route('ia-profiles.deletion-requests.index'),
                'lipa_count'     => $p->lipa_entries_count,
                'lipa_url'       => route('ia-profiles.lipa.index', $p),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function create(Request $request): View
    {
        $this->requirePermission('ia.create');
        $me = $request->user();

        return view('ia-profiles.create', $this->formData($me, new IaProfile([
            'status'    => 'operational',
            'office_id' => IaAccess::regionWide($me) ? null : $me->office_id,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePermission('ia.create');
        $me = $request->user();
        $this->forceOffice($request, $me);

        $data = IaProfileRules::normalise(
            $request->validate(IaProfileRules::rules($me, null, $request->all()), IaProfileRules::messages())
        );

        $profile = new IaProfile($data);
        $profile->forceFill(['created_by' => $me->id, 'updated_by' => $me->id])->save();

        IaProfileAudit::created($profile);

        return redirect()
            ->route('ia-profiles.show', $profile)
            ->with('status', __('IA profile :name was created.', ['name' => $profile->ia_name]));
    }

    public function show(Request $request, IaProfile $iaProfile): View
    {
        $this->requirePermission('ia.view');
        $me = $request->user();

        $iaProfile->load(['office', 'province', 'district', 'assignedUser', 'updater:id,first_name,last_name',
            'officers' => fn ($q) => $q->orderBy('id')]);

        $history = AuditLog::query()
            ->with('user:id,first_name,last_name')
            ->where('entity_type', IaProfileAudit::ENTITY)
            ->where('entity_id', $iaProfile->id)
            ->latest('id')
            ->limit(50)
            ->get();

        // LIPA at a glance: one line per crop year + season.
        $lipaSummary = $iaProfile->lipaEntries()
            ->selectRaw('crop_year, season, COUNT(*) AS lots, SUM(service_area_ha) AS service_area, SUM(irrigated_planted_area_ha) AS planted_area')
            ->groupBy('crop_year', 'season')
            ->orderByDesc('crop_year')->orderBy('season')
            ->get();

        // An archived profile is read-only: no edit, no officer changes, only Restore.
        $archived = $iaProfile->trashed();

        return view('ia-profiles.show', [
            'profile'     => $iaProfile,
            'history'     => $history,
            'archived'    => $archived,
            'canEdit'     => ! $archived && $me->hasPermission('ia.edit'),
            'canDelete'   => ! $archived && $me->hasPermission('ia.delete'),
            'canRestore'  => $archived && $me->hasPermission('ia.delete'),
            'lipaSummary' => $lipaSummary,
            'hasMap'      => \App\Models\IaMapLot::where('ia_profile_id', $iaProfile->id)->exists(),
            'deleteMode'  => IaAccess::canApproveDeletion($me) ? 'direct' : 'request',
            'pendingDeletion' => $archived ? null : IaDeletionRequest::with('requester:id,first_name,last_name')
                ->where('ia_profile_id', $iaProfile->id)->where('status', 'pending')->first(),
            'labels'      => IaProfileAudit::LABELS,
        ]);
    }

    public function edit(Request $request, IaProfile $iaProfile): View
    {
        $this->requirePermission('ia.edit');

        return view('ia-profiles.edit', $this->formData($request->user(), $iaProfile));
    }

    public function update(Request $request, IaProfile $iaProfile): RedirectResponse
    {
        $this->requirePermission('ia.edit');
        $me = $request->user();
        $this->forceOffice($request, $me);

        $data = IaProfileRules::normalise(
            $request->validate(IaProfileRules::rules($me, $iaProfile, $request->all()), IaProfileRules::messages())
        );

        $iaProfile->fill($data);
        $changes = IaProfileAudit::changes($iaProfile);

        if ($changes === []) {
            return redirect()->route('ia-profiles.show', $iaProfile)->with('status', __('No changes were made.'));
        }

        $iaProfile->forceFill(['updated_by' => $me->id])->save();
        IaProfileAudit::updated($iaProfile, $changes);

        return redirect()
            ->route('ia-profiles.show', $iaProfile)
            ->with('status', __('IA profile :name was updated.', ['name' => $iaProfile->ia_name]));
    }

    /** "Delete" archives the profile (soft delete): it disappears from lists but the history is kept. */
    public function destroy(Request $request, IaProfile $iaProfile): JsonResponse|RedirectResponse
    {
        $this->requirePermission('ia.delete');
        // Only Super Admin / Regional Staff may delete directly; the others must use a deletion request.
        abort_unless(IaAccess::canApproveDeletion($request->user()), 403, __('Deleting an IA profile needs approval. Send a deletion request instead.'));

        // Deleting directly settles any request that was waiting for this profile.
        IaDeletionRequest::where('ia_profile_id', $iaProfile->id)->where('status', 'pending')->update([
            'status' => 'approved', 'decided_by' => $request->user()->id,
            'decision_note' => 'Archived directly by an approver.', 'decided_at' => now(),
        ]);

        IaProfileAudit::archived($iaProfile);
        $iaProfile->forceFill(['updated_by' => $request->user()->id])->save();
        $iaProfile->delete();

        $message = __('IA profile :name was archived.', ['name' => $iaProfile->ia_name]);

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('ia-profiles.index')->with('status', $message);
    }

    /* ------------------------------------------------------------------ archive */

    /** The archive: every profile that was "deleted" (soft-deleted). Needs ia.view; restoring needs ia.delete. */
    public function archived(Request $request): View
    {
        $this->requirePermission('ia.view');

        return view('ia-profiles.archived', [
            'showOffice' => IaAccess::regionWide($request->user()),
        ]);
    }

    public function archivedData(Request $request): JsonResponse
    {
        $this->requirePermission('ia.view');
        $canRestore = $request->user()->hasPermission('ia.delete');

        $rows = IaProfile::onlyTrashed()
            ->with(['office:id,code', 'province:id,name', 'updater:id,first_name,last_name'])
            ->withCount('lipaEntries')
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn (IaProfile $p) => [
                'id'          => $p->id,
                'ia_name'     => $p->ia_name,
                'ia_code'     => $p->ia_code,
                'province'    => $p->province?->name,
                'office'      => $p->office?->code,
                'status_label' => IaProfile::STATUSES[$p->status] ?? $p->status,
                'lipa_count'  => $p->lipa_entries_count,
                // The archive action saves updated_by just before deleting, so this is who archived it.
                'archived_by' => $p->updater?->name,
                'archived_at' => $p->deleted_at?->format('Y-m-d H:i'),
                'can_restore' => $canRestore,
                'show_url'    => route('ia-profiles.show', $p),
                'restore_url' => route('ia-profiles.restore', $p->id),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    /** Bring an archived profile back. Refused when an active profile has since taken its name or code. */
    public function restore(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $this->requirePermission('ia.delete');

        $profile = IaProfile::onlyTrashed()->findOrFail($id);

        $errors = [];

        $nameTaken = IaProfile::withoutGlobalScope(OfficeScope::class)
            ->where('id', '!=', $profile->id)
            ->where('province_id', $profile->province_id)
            ->where('ia_name', $profile->ia_name)
            ->exists();

        if ($nameTaken) {
            $errors['ia_name'] = [__('An active IA named ":name" already exists in that province, so this one cannot be restored. Rename or archive the other one first.', ['name' => $profile->ia_name])];
        }

        if ($profile->ia_code && IaProfile::withoutGlobalScope(OfficeScope::class)
            ->where('id', '!=', $profile->id)->where('ia_code', $profile->ia_code)->exists()) {
            $errors['ia_code'] = [__('The IA code :code is now used by another active IA.', ['code' => $profile->ia_code])];
        }

        if ($errors !== []) {
            return response()->json(['message' => __('This IA profile cannot be restored.'), 'errors' => $errors], 422);
        }

        $profile->restore();
        $profile->forceFill(['updated_by' => $request->user()->id])->save();
        IaProfileAudit::restored($profile);

        $message = __('IA profile :name was restored.', ['name' => $profile->ia_name]);

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('ia-profiles.show', $profile)->with('status', $message);
    }

    /* ------------------------------------------------------------------ */

    /** Office users cannot choose an office: whatever was posted, theirs is used. */
    private function forceOffice(Request $request, User $me): void
    {
        if (! IaAccess::regionWide($me)) {
            $request->merge(['office_id' => $me->office_id]);
        }
    }

    private function formData(User $me, IaProfile $profile): array
    {
        return [
            'profile'    => $profile,
            'offices'    => IaAccess::allowedOffices($me),
            'regionWide' => IaAccess::regionWide($me),
            'statuses'   => IaProfile::STATUSES,
            'geo'        => $this->geo($me),
        ];
    }

    /**
     * Lookup data for the dependent dropdowns (office -> provinces -> districts, office -> users).
     * Kept small on purpose: a region has only a handful of offices, provinces and districts.
     */
    private function geo(User $me): array
    {
        $offices = IaAccess::allowedOffices($me);
        $officeIds = $offices->pluck('id')->all();

        $coverage = DB::table('office_provinces')
            ->whereIn('office_id', $officeIds)
            ->get()
            ->groupBy('office_id')
            ->map(fn ($rows) => $rows->pluck('province_id')->map(fn ($id) => (int) $id)->values());

        $provinceIds = $coverage->flatten()->unique()->values()->all();

        return [
            'officeProvinces' => $coverage,
            'provinces'       => Province::whereIn('id', $provinceIds)->orderBy('name')->pluck('name', 'id'),
            'districts'       => CongressionalDistrict::whereIn('province_id', $provinceIds)
                ->orderBy('code')->get(['id', 'province_id', 'name'])
                ->groupBy('province_id')
                ->map(fn ($rows) => $rows->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values()),
            'users'           => User::where('is_active', true)->whereIn('office_id', $officeIds)
                ->orderBy('last_name')->orderBy('first_name')->get()
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'office_id' => $u->office_id])->values(),
        ];
    }

    private function requirePermission(string $code): void
    {
        abort_unless(auth()->user()?->hasPermission($code), 403);
    }
}
