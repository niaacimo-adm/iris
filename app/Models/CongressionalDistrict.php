<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CongressionalDistrict extends Model
{
    public $timestamps = false;

    protected $table = 'congressional_districts';

    protected $fillable = [
        'province_id',
        'code',
        'name',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
