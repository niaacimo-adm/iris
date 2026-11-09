<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    // The provinces table has no created_at / updated_at.
    public $timestamps = false;

    protected $table = 'provinces';

    protected $fillable = [
        'region_id',
        'code',
        'name',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function districts(): HasMany
    {
        return $this->hasMany(CongressionalDistrict::class);
    }

    /** IMOs that cover this province (office_provinces pivot). */
    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(
            Office::class,
            'office_provinces',
            'province_id',
            'office_id'
        );
    }
}
