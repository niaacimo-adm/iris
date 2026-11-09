<?php

namespace App\Models;

use App\Models\Scopes\OfficeScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class IaProfile extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'operational' => 'Operational',
        'non-operational' => 'Non-operational',
    ];

    protected $table = 'ia_profiles';

    protected $fillable = [
        'office_id', 'province_id', 'district_id', 'ia_name', 'ia_code', 'cis_name', 'nis', 'spip_name',
        'mailing_address', 'president_name', 'contact_number', 'date_organized',
        'sec_registration_date', 'sec_registration_number', 'ia_tin', 'service_area_ha', 'fusa_ha',
        'farmer_beneficiaries', 'actual_ia_members', 'male_members', 'female_members', 'tsags_count',
        'existing_contract', 'contract_effectivity_date', 'canal_length_km', 'status', 'assigned_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date_organized' => 'date',
            'sec_registration_date' => 'date',
            'contract_effectivity_date' => 'date',
            'service_area_ha' => 'decimal:4',
            'fusa_ha' => 'decimal:4',
            'canal_length_km' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OfficeScope);

        // Whatever the form says, an office user can only ever save into their own office.
        static::saving(function (self $profile) {
            $user = Auth::user();

            if ($user && $user->role?->data_scope !== 'region') {
                $profile->office_id = $user->office_id;
            }
        });
    }

    /** The irrigation systems this IA has, taken from the names filled in: CIS, NIS, SPIP (solar power irrigation pump). */
    public function systems(): array
    {
        return array_keys(array_filter([
            'CIS' => filled($this->cis_name),
            'NIS' => filled($this->nis),
            'SPIP' => filled($this->spip_name),
        ]));
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(CongressionalDistrict::class, 'district_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lipaEntries(): HasMany
    {
        return $this->hasMany(IaLipaEntry::class, 'ia_profile_id');
    }

    public function officers(): HasMany
    {
        return $this->hasMany(IaOfficer::class);
    }
}
