<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\IaDeletionRequest;
use App\Models\IaProfile;
use App\Support\IaAccess;
use App\Support\IaProfileAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Deleting an IA profile needs approval.
 *   IMO users (ia.delete)            -> send a request with a reason
 *   Super Admin / Regional Staff     -> are told (badge + this page), then approve (profile is archived) or reject
 * "Delete" always means Archive: nothing is physically removed and the profile can be restored from the archive.
 */
class IaDeletionRequestController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('ia.view'), 403);

        return view('ia-profiles.deletion-requests', [
            'isApprover' => IaAccess::canApproveDeletion($request->user()),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('ia.view'), 403);
        $me = $request->user();
        $approver = IaAccess::canApproveDeletion($me);

        $requests = IaDeletionRequest::query()
            ->with(['profile.office:id,code', 'profile.province:id,name', 'requester:id,first_name,last_name', 'decider:id,first_name,last_name'])
            ->orderByRaw("FIELD(status, 'pending') DESC")
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $rows = $requests->map(fn (IaDeletionRequest $r) => [
            'id' => $r->id,
            'ia_name' => $r->profile?->ia_name,
            'ia_code' => $r->profile?->ia_code,
            'province' => $r->profile?->province?->name,
            'office' => $r->profile?->office?->code,
            'requested_by' => $r->requester?->name,
            'reason' => $r->reason,
            'status' => $r->status,
            'status_label' => IaDeletionRequest::STATUSES[$r->status] ?? $r->status,
            'requested_at' => $r->created_at?->format('Y-m-d H:i'),
            'decided_by' => $r->decider?->name,
            'decision_note' => $r->decision_note,
            'decided_at' => $r->decided_at?->format('Y-m-d H:i'),
            'is_new' => ! $approver && (int) $r->requested_by === (int) $me->id
                && in_array($r->status, ['approved', 'rejected'], true) && $r->requester_seen_at === null,
            'can_decide' => $approver && $r->status === 'pending',
            'can_withdraw' => $r->status === 'pending' && (int) $r->requested_by === (int) $me->id,
            'show_url' => $r->profile ? route('ia-profiles.show', $r->ia_profile_id) : null,
            'approve_url' => route('ia-profiles.deletion-requests.approve', $r),
            'reject_url' => route('ia-profiles.deletion-requests.reject', $r),
            'withdraw_url' => route('ia-profiles.deletion-requests.withdraw', $r),
        ])->values();

        // The requester has now been shown the decisions: clear their badge.
        if (! $approver) {
            IaDeletionRequest::where('requested_by', $me->id)
                ->whereIn('status', ['approved', 'rejected'])
                ->whereNull('requester_seen_at')
                ->update(['requester_seen_at' => now()]);
        }

        return response()->json(['data' => $rows]);
    }

    /** An IMO user asks for a profile to be deleted. */
    public function store(Request $request, IaProfile $iaProfile): JsonResponse
    {
        $me = $request->user();
        abort_unless($me->hasPermission('ia.delete'), 403);
        abort_if(IaAccess::canApproveDeletion($me), 403, __('Super Admin and Regional Staff delete directly; they do not need to ask.'));

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => __('Tell the approvers why this IA profile should be deleted.'),
            'reason.min' => __('Please give a clearer reason (at least 10 characters).'),
        ]);

        if (IaDeletionRequest::where('ia_profile_id', $iaProfile->id)->where('status', 'pending')->exists()) {
            return response()->json([
                'message' => __('Already requested.'),
                'errors' => ['reason' => [__('A deletion request for this IA profile is already waiting for approval.')]],
            ], 422);
        }

        $deletion = new IaDeletionRequest;
        $deletion->forceFill([
            'ia_profile_id' => $iaProfile->id,
            'office_id' => $iaProfile->office_id,
            'requested_by' => $me->id,
            'reason' => trim($data['reason']),
            'status' => 'pending',
        ])->save();

        AuditLog::record('updated', IaProfileAudit::ENTITY, $iaProfile->id,
            "Requested deletion of IA profile \"{$iaProfile->ia_name}\": ".$deletion->reason);

        $count = IaAccess::approvers()->count();

        return response()->json([
            'message' => __('Deletion requested. :n Super Admin / Regional Staff user(s) will see it and decide.', ['n' => $count]),
        ], 201);
    }

    public function approve(Request $request, IaDeletionRequest $deletionRequest): JsonResponse
    {
        $me = $request->user();
        abort_unless(IaAccess::canApproveDeletion($me), 403);

        $note = $this->note($request, false);
        $this->ensurePending($deletionRequest);

        $profile = IaProfile::find($deletionRequest->ia_profile_id);

        if (! $profile) {
            return response()->json([
                'message' => __('Already archived.'),
                'errors' => ['request' => [__('This IA profile is already archived.')]],
            ], 422);
        }

        DB::transaction(function () use ($deletionRequest, $profile, $me, $note) {
            IaProfileAudit::archived($profile);
            $profile->forceFill(['updated_by' => $me->id])->save();
            $profile->delete();

            $deletionRequest->forceFill([
                'status' => 'approved', 'decided_by' => $me->id, 'decision_note' => $note, 'decided_at' => now(),
            ])->save();
        });

        return response()->json(['message' => __('Approved. :name was archived.', ['name' => $profile->ia_name])]);
    }

    public function reject(Request $request, IaDeletionRequest $deletionRequest): JsonResponse
    {
        $me = $request->user();
        abort_unless(IaAccess::canApproveDeletion($me), 403);

        $note = $this->note($request, true);
        $this->ensurePending($deletionRequest);

        $deletionRequest->forceFill([
            'status' => 'rejected', 'decided_by' => $me->id, 'decision_note' => $note, 'decided_at' => now(),
        ])->save();

        if ($deletionRequest->profile) {
            AuditLog::record('updated', IaProfileAudit::ENTITY, $deletionRequest->ia_profile_id,
                "Deletion of IA profile \"{$deletionRequest->profile->ia_name}\" was rejected: ".$note);
        }

        return response()->json(['message' => __('Request rejected. The requester will see your reason.')]);
    }

    public function withdraw(Request $request, IaDeletionRequest $deletionRequest): JsonResponse
    {
        abort_unless((int) $deletionRequest->requested_by === (int) $request->user()->id, 403);
        $this->ensurePending($deletionRequest);

        $deletionRequest->forceFill(['status' => 'withdrawn', 'decided_at' => now(), 'requester_seen_at' => now()])->save();

        return response()->json(['message' => __('Your deletion request was withdrawn.')]);
    }

    /* ------------------------------------------------------------------ */

    private function note(Request $request, bool $required): ?string
    {
        $data = $request->validate([
            'note' => [$required ? 'required' : 'nullable', 'string', $required ? 'min:5' : 'min:0', 'max:500'],
        ], [
            'note.required' => __('Give the requester a reason for rejecting.'),
            'note.min' => __('Please give a clearer reason (at least 5 characters).'),
        ]);

        return isset($data['note']) && trim($data['note']) !== '' ? trim($data['note']) : null;
    }

    /** Someone else may have decided it already. */
    private function ensurePending(IaDeletionRequest $deletionRequest): void
    {
        abort_unless($deletionRequest->status === 'pending', 409, __('This request was already :status.', [
            'status' => strtolower(IaDeletionRequest::STATUSES[$deletionRequest->status] ?? $deletionRequest->status),
        ]));
    }
}
