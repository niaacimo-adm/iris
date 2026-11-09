<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IaOfficer extends Model
{
    // The ia_officers table only has created_at (no updated_at).
    const UPDATED_AT = null;

    protected $table = 'ia_officers';

    protected $fillable = ['ia_profile_id', 'officer_name', 'position', 'contact_number', 'email', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** An officer is visible only when the IA profile it belongs to is visible to the current user (archived profiles included, so the archive view can show them). */
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
        return $this->belongsTo(IaProfile::class, 'ia_profile_id');
    }
}
