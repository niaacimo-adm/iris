<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One LIPA line (a lot / landowner) of an IA for one crop year and season. Table: ia_lipa_entries. */
class IaLipaEntry extends Model
{
    public const SEASONS = ['wet' => 'Wet', 'dry' => 'Dry'];

    protected $table = 'ia_lipa_entries';

    protected $fillable = [
        'crop_year', 'season', 'sector', 'no', 'lot_no',
        'landowner_first_name', 'landowner_mi', 'landowner_last_name',
        'service_area_ha', 'irrigated_planted_area_ha', 'variety_inbred', 'variety_hybrid',
        'date_sown', 'date_planted', 'expected_harvest_date', 'rsbsa_reg_no', 'crop_insurance', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'crop_year' => 'integer',
            'no' => 'integer',
            'service_area_ha' => 'decimal:4',
            'irrigated_planted_area_ha' => 'decimal:4',
            'date_sown' => 'date',
            'date_planted' => 'date',
            'expected_harvest_date' => 'date',
        ];
    }

    /**
     * A LIPA line is visible only when its IA profile is visible to the current user
     * (same office rule as the profile). Archived profiles are included so their LIPA can still be read.
     */
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** "Villareal, Alvin M." */
    public function landownerName(): string
    {
        $first = trim(($this->landowner_first_name ?? '').' '.($this->landowner_mi ? rtrim($this->landowner_mi, '.').'.' : ''));

        return trim(collect([$this->landowner_last_name, $first !== '' ? $first : null])->filter()->implode(', '));
    }
}
