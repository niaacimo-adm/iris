<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One lot (polygon) of an IA's service area, for the map. Table: ia_map_lots. */
class IaMapLot extends Model
{
    public const STATUSES = [
        'op' => 'Operational / irrigated',
        'non_op' => 'Non-operational',
        'converted' => 'Converted',
        'da_area' => 'DA area',
        'pnra' => 'PNRA',
    ];

    protected $table = 'ia_map_lots';

    protected $fillable = ['ia_profile_id', 'lot_name', 'area_ha', 'lot_status', 'remarks', 'municity', 'geometry', 'source'];

    /** Only lots of IA profiles the user may see (office rule) and that are not archived. */
    protected static function booted(): void
    {
        static::addGlobalScope('through_profile', function (Builder $builder) {
            $builder->whereIn($builder->getModel()->qualifyColumn('ia_profile_id'), IaProfile::query()->select('id'));
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(IaProfile::class, 'ia_profile_id');
    }
}
