<?php

namespace App\Support;

use App\Models\IaLipaEntry;
use App\Models\IaProfile;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Validation for one LIPA record (add and edit).
 *
 * Imported (legacy) rows sometimes break the date / area order rules, so those cross-checks only run
 * when the record is new or when one of the fields involved was actually changed. That way a remark
 * on an old row can still be corrected without first fixing its dates.
 */
class IaLipaRules
{
    private const RSBSA = '/^[A-Za-z0-9\-\s\/]{5,50}$/';

    private const DECIMALS = ['service_area_ha', 'irrigated_planted_area_ha'];

    /** @throws \Illuminate\Validation\ValidationException */
    public static function validate(array $input, IaProfile $profile, ?IaLipaEntry $entry = null): array
    {
        $input = self::prepare($input);

        $validator = Validator::make($input, self::rules($profile, $entry, $input), self::messages(), self::attributes());

        $validator->after(function (ValidatorContract $v) use ($entry, $input) {
            if ($v->errors()->isEmpty()) {
                self::crossChecks($v, $input, $entry);
            }
        });

        return self::normalise($validator->validate());
    }

    public static function rules(IaProfile $profile, ?IaLipaEntry $entry, array $input): array
    {
        // Same crop year + season + sector + lot number cannot be entered twice for one IA.
        $lotRules = ['nullable', 'string', 'max:30'];

        if (self::changed($entry, ['crop_year', 'season', 'sector', 'lot_no'], $input)) {
            $lotRules[] = Rule::unique('ia_lipa_entries', 'lot_no')
                ->where(fn ($q) => $q->where('ia_profile_id', $profile->id)
                    ->where('crop_year', (int) ($input['crop_year'] ?? 0))
                    ->where('season', (string) ($input['season'] ?? ''))
                    ->where('sector', (string) ($input['sector'] ?? '')))
                ->ignore($entry?->id);
        }

        return [
            'crop_year' => ['required', 'integer', 'between:2000,'.((int) now()->year + 1)],
            'season' => ['required', Rule::in(array_keys(IaLipaEntry::SEASONS))],
            'sector' => ['required', 'string', 'max:50'],
            'no' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'lot_no' => $lotRules,

            'landowner_first_name' => ['required', 'string', 'max:100'],
            'landowner_mi' => ['nullable', 'string', 'max:10'],
            'landowner_last_name' => ['required', 'string', 'max:100'],

            'service_area_ha' => ['required', 'numeric', 'min:0', 'max:999999.9999'],
            'irrigated_planted_area_ha' => ['required', 'numeric', 'min:0', 'max:999999.9999'],

            'variety_inbred' => ['nullable', 'string', 'max:30'],
            'variety_hybrid' => ['nullable', 'string', 'max:30'],

            'date_sown' => ['nullable', 'date'],
            'date_planted' => ['nullable', 'date'],
            'expected_harvest_date' => ['nullable', 'date'],

            'rsbsa_reg_no' => ['nullable', 'string', 'regex:'.self::RSBSA],
            'crop_insurance' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function messages(): array
    {
        return [
            'crop_year.between' => 'The crop year must be between 2000 and '.((int) now()->year + 1).'.',
            'season.in' => 'Season must be Wet or Dry.',
            'lot_no.unique' => 'This lot number is already recorded for the same crop year, season and sector.',
            'rsbsa_reg_no.regex' => 'RSBSA registration no.: use letters, digits, dashes and / only (5 to 50 characters).',
            'service_area_ha.max' => 'The service area is too large.',
            'irrigated_planted_area_ha.max' => 'The irrigated planted area is too large.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'crop_year' => 'crop year',
            'season' => 'season',
            'sector' => 'sector',
            'no' => 'No.',
            'lot_no' => 'lot number',
            'landowner_first_name' => 'landowner first name',
            'landowner_mi' => 'landowner middle initial',
            'landowner_last_name' => 'landowner last name',
            'service_area_ha' => 'service area',
            'irrigated_planted_area_ha' => 'irrigated planted area',
            'variety_inbred' => 'inbred variety',
            'variety_hybrid' => 'hybrid variety',
            'date_sown' => 'date sown',
            'date_planted' => 'date planted',
            'expected_harvest_date' => 'expected harvest date',
            'rsbsa_reg_no' => 'RSBSA registration no.',
            'crop_insurance' => 'crop insurance',
            'remarks' => 'remarks',
        ];
    }

    /** Trim text, turn '' into null, allow "1,234.5" in the area fields. */
    private static function prepare(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $value = trim(preg_replace('/[ \t]+/', ' ', $value));
                $input[$key] = $value === '' ? null : $value;
            }
        }

        foreach (self::DECIMALS as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = str_replace(',', '', $input[$field]);
            }
        }

        if (is_string($input['season'] ?? null)) {
            $input['season'] = strtolower($input['season']);
        }

        return $input;
    }

    private static function crossChecks(ValidatorContract $v, array $input, ?IaLipaEntry $entry): void
    {
        if (self::changed($entry, self::DECIMALS, $input)
            && (float) $input['irrigated_planted_area_ha'] > (float) $input['service_area_ha'] + 0.00005) {
            $v->errors()->add('irrigated_planted_area_ha', 'The irrigated planted area cannot be more than the service area.');
        }

        $sown = $input['date_sown'] ?? null;
        $planted = $input['date_planted'] ?? null;
        $harvest = $input['expected_harvest_date'] ?? null;

        if ($sown && $planted && self::changed($entry, ['date_sown', 'date_planted'], $input)
            && strtotime($planted) < strtotime($sown)) {
            $v->errors()->add('date_planted', 'The date planted cannot be earlier than the date sown.');
        }

        if ($planted && $harvest && self::changed($entry, ['date_planted', 'expected_harvest_date'], $input)
            && strtotime($harvest) < strtotime($planted)) {
            $v->errors()->add('expected_harvest_date', 'The expected harvest date cannot be earlier than the date planted.');
        }
    }

    /** True for a new record, or when any of $fields differs from what is stored. */
    private static function changed(?IaLipaEntry $entry, array $fields, array $input): bool
    {
        if ($entry === null) {
            return true;
        }

        foreach ($fields as $field) {
            $old = $entry->getAttribute($field);
            $old = $old instanceof CarbonInterface ? $old->format('Y-m-d') : $old;
            $new = $input[$field] ?? null;

            if (in_array($field, self::DECIMALS, true)) {
                if (abs((float) $old - (float) $new) > 0.00005) {
                    return true;
                }

                continue;
            }

            if ((string) ($old ?? '') !== (string) ($new ?? '')) {
                return true;
            }
        }

        return false;
    }

    private static function normalise(array $data): array
    {
        foreach (self::DECIMALS as $field) {
            $data[$field] = round((float) $data[$field], 4);
        }

        $data['crop_year'] = (int) $data['crop_year'];

        if (isset($data['no']) && $data['no'] !== '') {
            $data['no'] = (int) $data['no'];
        }

        return $data;
    }
}
