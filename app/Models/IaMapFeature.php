<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One piece of an IA's canal network (a canal line, a structure / dam / damage point). Table: ia_map_features. */
class IaMapFeature extends Model
{
    public const KINDS = [
        'canal' => 'Canal',
        'structure' => 'Structure',
        'dam' => 'Dam / intake',
        'damage' => 'Damage (for repair)',
    ];

    protected $table = 'ia_map_features';

    protected $fillable = ['ia_profile_id', 'kind', 'name', 'group_name', 'length_m', 'geometry', 'source'];

    /** Only features of IA profiles the user may see (office rule) and that are not archived. */
    protected static function booted(): void
    {
        static::addGlobalScope('through_profile', function (Builder $builder) {
            $builder->whereIn($builder->getModel()->qualifyColumn('ia_profile_id'), IaProfile::query()->select('id'));
        });
    }
}
