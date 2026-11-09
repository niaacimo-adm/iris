<?php

namespace App\Models;

use App\Support\IaAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A request to delete (archive) an IA profile. Decided by Super Admin / Regional Staff. */
class IaDeletionRequest extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn'];

    protected $table = 'ia_deletion_requests';

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'requester_seen_at' => 'datetime'];
    }

    /** Same office rule as the profile it is about (archived profiles included). */
    protected static function booted(): void
    {
        static::addGlobalScope('through_profile', function (Builder $builder) {
            $builder->whereIn(
                $builder->getModel()->qualifyColumn('ia_profile_id'),
                IaProfile::withTrashed()->select('id')
            );
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(IaProfile::class, 'ia_profile_id')->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * The number on the sidebar badge.
     *   Super Admin / Regional Staff -> requests waiting for a decision
     *   everyone else                -> decisions on their own requests they have not looked at yet
     */
    public static function badgeCount(?User $user): int
    {
        if (! $user || ! $user->hasPermission('ia.view')) {
            return 0;
        }

        if (IaAccess::canApproveDeletion($user)) {
            return static::where('status', 'pending')->count();
        }

        return static::where('requested_by', $user->id)
            ->whereIn('status', ['approved', 'rejected'])
            ->whereNull('requester_seen_at')
            ->count();
    }
}
