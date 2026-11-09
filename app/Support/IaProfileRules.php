<?php

namespace App\Support;

use App\Models\IaProfile;
use App\Models\User;
use Illuminate\Validation\Rule;

/** Validation for the IA profile form (create and edit). */
class IaProfileRules
{
    private const PHONE = '/^[0-9+\-\s()\/,]{5,20}$/';
    private const TIN = '/^[0-9\-\s]{9,20}$/';

    /**
     * @param  array  $input  the submitted data (office_id must already be forced for office users)
     */
    public static function rules(User $me, ?IaProfile $profile, array $input): array
    {
        $officeId = (int) ($input['office_id'] ?? 0);
        $provinceId = (int) ($input['province_id'] ?? 0);

        return [
            'office_id' => ['required', 'integer', Rule::in(IaAccess::allowedOffices($me)->pluck('id')->all())],

            // The province must be one that this office covers, the district one of that province.
            'province_id' => ['required', 'integer',
                Rule::exists('office_provinces', 'province_id')->where('office_id', $officeId)],
            'district_id' => ['required', 'integer',
                Rule::exists('congressional_districts', 'id')->where('province_id', $provinceId)],

            'ia_name' => ['required', 'string', 'max:255',
                Rule::unique('ia_profiles', 'ia_name')
                    ->where(fn ($q) => $q->where('province_id', $provinceId)->whereNull('deleted_at'))
                    ->ignore($profile?->id)],
            'ia_code' => ['nullable', 'string', 'max:50', Rule::unique('ia_profiles', 'ia_code')->ignore($profile?->id)],
            'cis_name' => ['nullable', 'string', 'max:150'],
            'nis' => ['nullable', 'string', 'max:100'],
            'spip_name' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(array_keys(IaProfile::STATUSES))],

            'mailing_address' => ['nullable', 'string', 'max:1000'],
            'president_name' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'regex:'.self::PHONE],
            'assigned_user_id' => ['nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('office_id', $officeId)->where('is_active', 1))],

            'date_organized' => ['nullable', 'date', 'before_or_equal:today'],
            'sec_registration_date' => ['nullable', 'date', 'before_or_equal:today'],
            'sec_registration_number' => ['nullable', 'string', 'max:100'],
            'ia_tin' => ['nullable', 'string', 'regex:'.self::TIN],
            'existing_contract' => ['nullable', 'string', 'max:100'],
            'contract_effectivity_date' => ['nullable', 'date'],

            'service_area_ha' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'fusa_ha' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'canal_length_km' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
            'farmer_beneficiaries' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'male_members' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'female_members' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'tsags_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'actual_ia_members' => ['nullable', 'integer', 'min:0', 'max:1000000',
                // When all three are given, the total must equal male + female.
                function (string $attribute, mixed $value, \Closure $fail) use ($input) {
                    $male = $input['male_members'] ?? null;
                    $female = $input['female_members'] ?? null;

                    if (self::filled($male) && self::filled($female) && (int) $value !== (int) $male + (int) $female) {
                        $fail('Actual IA members must equal male + female members ('.((int) $male + (int) $female).').');
                    }
                }],
        ];
    }

    public static function messages(): array
    {
        return [
            'province_id.exists' => 'That province is not covered by the selected office.',
            'district_id.exists' => 'That district does not belong to the selected province.',
            'ia_name.unique' => 'An IA with this name already exists in that province.',
            'ia_code.unique' => 'This IA code is already used by another IA.',
            'contact_number.regex' => 'Use digits and + - ( ) / , only (5 to 20 characters).',
            'ia_tin.regex' => 'TIN: use digits and dashes only, for example 123-456-789-000.',
            'assigned_user_id.exists' => 'The assigned user must be an active user of the same office.',
            'office_id.in' => 'You cannot use that office.',
        ];
    }

    /** Tidy the validated data before saving: trim text, round numbers, fill the member total. */
    public static function normalise(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $value = trim(preg_replace('/[ \t]+/', ' ', $value));
                $data[$key] = $value === '' ? null : $value;
            }
        }

        foreach (['service_area_ha' => 4, 'fusa_ha' => 4, 'canal_length_km' => 3] as $field => $places) {
            if (self::filled($data[$field] ?? null)) {
                $data[$field] = round((float) $data[$field], $places);
            }
        }

        if (! self::filled($data['actual_ia_members'] ?? null)
            && self::filled($data['male_members'] ?? null) && self::filled($data['female_members'] ?? null)) {
            $data['actual_ia_members'] = (int) $data['male_members'] + (int) $data['female_members'];
        }

        return $data;
    }

    private static function filled(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
