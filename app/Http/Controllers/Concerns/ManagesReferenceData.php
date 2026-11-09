<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Shared rules for the regions / provinces / offices screens.
 *
 * All three are region-wide reference data, so on top of the existing
 * `office.manage` permission ("Manage offices and reference data") they are
 * limited to super admins. Delete the isSuperAdmin() check if you want to
 * delegate it to other roles.
 */
trait ManagesReferenceData
{
    protected function authorizeReferenceData(): void
    {
        $me = auth()->user();

        abort_unless($me && $me->hasPermission('office.manage') && $me->isSuperAdmin(), 403);
    }

    /** Codes are stored upper-case (V, ALB, RO5, ACIMO). */
    protected function normalizeCode(Request $request): void
    {
        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }
    }
}
