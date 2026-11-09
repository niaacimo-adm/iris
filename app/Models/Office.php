<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    /** Allowed offices.office_type values => label shown in the UI. */
    public const TYPES = [
        'regional' => 'Regional office',
        'imo'      => 'IMO',
    ];

    // The offices table only has created_at (no updated_at).
    const UPDATED_AT = null;

    protected $table = 'offices';

    protected $fillable = [
        'region_id',
        'parent_office_id',
        'code',
        'name',
        'office_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** The regional office an IMO reports to. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_office_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_office_id');
    }

    /** Provinces this office covers (office_provinces pivot). */
    public function provinces(): BelongsToMany
    {
        return $this->belongsToMany(
            Province::class,
            'office_provinces',
            'office_id',
            'province_id'
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
