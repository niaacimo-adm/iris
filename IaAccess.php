<?php

namespace App\Support;

use App\Models\Office;
use App\Models\User;
use Illuminate\Support\Collection;

/** Small helpers that decide which office(s) a user may work with. */
class IaAccess
{
    /** Region-wide roles (super admin, regional staff) see every office. */
    public static function regionWide(?User $user): bool
    {
        return $user?->role?->data_scope === 'region';
    }

    /** Roles that decide on IA profile delete requests (roles.code). */
    public const APPROVER_ROLES = ['super_admin', 'regional_staff'];

    public static function canApproveDeletion(?User $user): bool
    {
        return in_array($user?->role?->code, self::APPROVER_ROLES, true);
    }

    /** Active users who are told about a delete request. */
    public static function approvers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('code', self::APPROVER_ROLES))
            ->get();
    }

    /** Offices the user may create or move IA profiles into. */
    public static function allowedOffices(User $user): Collection
    {
        return Office::query()
            ->where('is_active', true)
            ->when(
                static::regionWide($user),
                fn ($q) => $q->where('office_type', 'imo'),
                fn ($q) => $q->where('id', $user->office_id ?? 0)
            )
            ->orderBy('name')
            ->get();
    }
}
