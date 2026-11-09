<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    // The regions table has no created_at / updated_at.
    public $timestamps = false;

    protected $table = 'regions';

    protected $fillable = [
        'code',
        'name',
    ];

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }
}
