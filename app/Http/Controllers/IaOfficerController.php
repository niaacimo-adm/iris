<?php

namespace App\Http\Controllers;

use App\Models\IaOfficer;
use App\Models\IaProfile;
use App\Support\IaProfileAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Add / edit / remove the officers of an IA, from the profile's page. Needs the ia.edit permission.
 * The profile page talks to these routes with fetch() (JSON): validation errors come back as 422 and are shown
 * in a SweetAlert. A plain form post still works and redirects back.
 */
class IaOfficerController extends Controller
{
    public function store(Request $request, IaProfile $iaProfile): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->hasPermission('ia.edit'), 403);

        $data = $this->validated($request, $iaProfile, null);

        $officer = $iaProfile->officers()->create($data + ['is_active' => true]);

        IaProfileAudit::officerAdded($iaProfile, $officer);

        return $this->done($request, $iaProfile, __('Officer :name was added.', ['name' => $officer->officer_name]));
    }

    public function update(Request $request, IaProfile $iaProfile, IaOfficer $officer): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->hasPermission('ia.edit'), 403);
        abort_unless((int) $officer->ia_profile_id === (int) $iaProfile->id, 404);

        $data = $this->validated($request, $iaProfile, $officer);

        $officer->fill($data);

        $changes = [];
        foreach (array_keys($officer->getDirty()) as $field) {
            $old = $officer->getOriginal($field);
            $new = $officer->getAttribute($field);

            if (($old ?? '') !== ($new ?? '')) {
                $changes[$field] = [$old === '' ? null : $old, $new === '' ? null : $new];
            }
        }

        if ($changes === []) {
            return $this->done($request, $iaProfile, __('No changes were made.'));
        }

        $officer->save();
        IaProfileAudit::officerUpdated($iaProfile, $officer, $changes);

        return $this->done($request, $iaProfile, __('Officer :name was updated.', ['name' => $officer->officer_name]));
    }

    public function destroy(Request $request, IaProfile $iaProfile, IaOfficer $officer): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->hasPermission('ia.edit'), 403);
        abort_unless((int) $officer->ia_profile_id === (int) $iaProfile->id, 404);

        $officer->delete();

        IaProfileAudit::officerRemoved($iaProfile, $officer);

        return $this->done($request, $iaProfile, __('Officer :name was removed.', ['name' => $officer->officer_name]));
    }

    /* ------------------------------------------------------------------ */

    private function validated(Request $request, IaProfile $profile, ?IaOfficer $officer): array
    {
        $request->merge(array_map(
            fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim(preg_replace('/[ \t]+/', ' ', $v))) : $v,
            $request->only(['officer_name', 'position', 'contact_number', 'email'])
        ));

        return $request->validate([
            'officer_name' => ['required', 'string', 'max:255',
                Rule::unique('ia_officers', 'officer_name')
                    ->where(fn ($q) => $q->where('ia_profile_id', $profile->id))
                    ->ignore($officer?->id)],
            'position' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'regex:/^[0-9+\-\s()\/,]{5,20}$/'],
            'email' => ['nullable', 'email', 'max:190'],
        ], [
            'officer_name.required' => __('Enter the officer\'s name.'),
            'officer_name.unique' => __('This officer is already listed for this IA.'),
            'contact_number.regex' => __('Use digits and + - ( ) / , only (5 to 20 characters).'),
        ]);
    }

    /** Fetch callers get JSON (the message is also flashed so the reloaded page shows it); forms get a redirect. */
    private function done(Request $request, IaProfile $profile, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            $request->session()->flash('status', $message);

            return response()->json(['message' => $message]);
        }

        return redirect()->route('ia-profiles.show', $profile)->with('status', $message);
    }
}
