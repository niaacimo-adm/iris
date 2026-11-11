<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\CongressionalDistrict;
use App\Models\IaLipaEntry;
use App\Models\IaOfficer;
use App\Models\IaProfile;
use App\Models\Office;
use App\Models\Province;
use App\Models\User;
use Carbon\CarbonInterface;

/** Writes the activity history shown on an IA profile (one line per changed field). */
class IaProfileAudit
{
    public const ENTITY = 'ia_profile';

    public const LABELS = [
        'office_id' => 'Office', 'province_id' => 'Province', 'district_id' => 'District',
        'ia_name' => 'IA name', 'ia_code' => 'IA code', 'cis_name' => 'Name of CIS', 'nis' => 'Name of NIS', 'spip_name' => 'Name of SPIP',
        'status' => 'Status', 'mailing_address' => 'Mailing address', 'president_name' => 'President',
        'contact_number' => 'Contact number', 'assigned_user_id' => 'Assigned to',
        'date_organized' => 'Date organized', 'sec_registration_date' => 'SEC registration date',
        'sec_registration_number' => 'SEC registration no.', 'ia_tin' => 'IA TIN',
        'existing_contract' => 'Existing contract', 'contract_effectivity_date' => 'Contract effectivity date',
        'service_area_ha' => 'Service area (ha)', 'fusa_ha' => 'FUSA (ha)', 'canal_length_km' => 'Canal length (km)',
        'farmer_beneficiaries' => 'Farmer beneficiaries', 'actual_ia_members' => 'Actual IA members',
        'male_members' => 'Male members', 'female_members' => 'Female members', 'tsags_count' => 'No. of TSAGs',
    ];

    public static function created(IaProfile $profile): void
    {
        AuditLog::record('created', self::ENTITY, $profile->id, "Created IA profile \"{$profile->ia_name}\"");
    }

    /**
     * Call AFTER fill() and BEFORE save(): returns [field => [old, new]] for the fields that really changed.
     *
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function changes(IaProfile $profile): array
    {
        $changes = [];

        foreach (array_keys(self::LABELS) as $field) {
            if (! $profile->isDirty($field)) {
                continue;
            }

            $old = self::display($field, $profile->getOriginal($field));
            $new = self::display($field, $profile->getAttribute($field));

            if ($old !== $new) {
                $changes[$field] = [$old, $new];
            }
        }

        return $changes;
    }

    /** Call AFTER a successful save, passing what changes() returned. */
    public static function updated(IaProfile $profile, array $changes): void
    {
        foreach ($changes as $field => [$old, $new]) {
            AuditLog::record('updated', self::ENTITY, $profile->id, 'Updated '.self::LABELS[$field], [
                'field_name' => $field, 'old_value' => $old, 'new_value' => $new,
            ]);
        }
    }

    public static function archived(IaProfile $profile): void
    {
        AuditLog::record('deleted', self::ENTITY, $profile->id, "Archived IA profile \"{$profile->ia_name}\"");
    }

    public static function officerAdded(IaProfile $profile, IaOfficer $officer): void
    {
        AuditLog::record('officer_added', self::ENTITY, $profile->id, "Added officer {$officer->officer_name}"
            .($officer->position ? " ({$officer->position})" : ''));
    }

    public static function officerRemoved(IaProfile $profile, IaOfficer $officer): void
    {
        AuditLog::record('officer_deleted', self::ENTITY, $profile->id, "Removed officer {$officer->officer_name}");
    }

    public static function mapUploaded(IaProfile $profile, int $lots, float $ha, string $mode, string $fileName, int $network = 0, float $canalKm = 0): void
    {
        $what = [];
        if ($lots > 0) {
            $what[] = ($mode === 'add' ? 'added ' : 'replaced the lots with ').$lots.' lot(s), '.number_format($ha, 2).' ha';
        }
        if ($network > 0) {
            $what[] = 'set the canal network ('.$network.' items, '.number_format($canalKm, 2).' km of canal)';
        }

        AuditLog::record('updated', self::ENTITY, $profile->id, 'Map: '.implode('; ', $what)." from \"{$fileName}\"");
    }

    public static function mapRemoved(IaProfile $profile, int $lots, int $network = 0): void
    {
        AuditLog::record('updated', self::ENTITY, $profile->id, "Removed the service area map ({$lots} lots, {$network} canal network items)");
    }

    public static function restored(IaProfile $profile): void
    {
        AuditLog::record('updated', self::ENTITY, $profile->id, "Restored IA profile \"{$profile->ia_name}\" from the archive");
    }

    /** Officer edit: one line per changed field. $changes = [field => [old, new]]. */
    public static function officerUpdated(IaProfile $profile, IaOfficer $officer, array $changes): void
    {
        $labels = ['officer_name' => 'Name', 'position' => 'Position', 'contact_number' => 'Contact number', 'email' => 'Email'];

        foreach ($changes as $field => [$old, $new]) {
            AuditLog::record('updated', self::ENTITY, $profile->id, "Updated officer {$officer->officer_name}: ".($labels[$field] ?? $field), [
                'field_name' => $field, 'old_value' => $old, 'new_value' => $new,
            ]);
        }
    }

    /* ---------------- LIPA records (shown in the profile's activity history) ---------------- */

    public const LIPA_LABELS = [
        'crop_year' => 'Crop year', 'season' => 'Season', 'sector' => 'Sector', 'no' => 'No.', 'lot_no' => 'Lot no.',
        'landowner_first_name' => 'Landowner first name', 'landowner_mi' => 'Landowner M.I.',
        'landowner_last_name' => 'Landowner last name', 'service_area_ha' => 'Service area (ha)',
        'irrigated_planted_area_ha' => 'Irrigated planted area (ha)', 'variety_inbred' => 'Inbred variety',
        'variety_hybrid' => 'Hybrid variety', 'date_sown' => 'Date sown', 'date_planted' => 'Date planted',
        'expected_harvest_date' => 'Expected harvest date', 'rsbsa_reg_no' => 'RSBSA reg. no.',
        'crop_insurance' => 'Crop insurance', 'remarks' => 'Remarks',
    ];

    public static function lipaCreated(IaProfile $profile, IaLipaEntry $entry): void
    {
        AuditLog::record('created', self::ENTITY, $profile->id, 'Added LIPA record: '.self::lipaTitle($entry));
    }

    /** Call AFTER fill() and BEFORE save(). Returns [field => [old, new]] for fields that really changed. */
    public static function lipaChanges(IaLipaEntry $entry): array
    {
        $changes = [];

        foreach (array_keys(self::LIPA_LABELS) as $field) {
            if (! $entry->isDirty($field)) {
                continue;
            }

            $old = self::lipaValue($field, $entry->getOriginal($field));
            $new = self::lipaValue($field, $entry->getAttribute($field));

            if ($old !== $new) {
                $changes[$field] = [$old, $new];
            }
        }

        return $changes;
    }

    public static function lipaUpdated(IaProfile $profile, IaLipaEntry $entry, array $changes): void
    {
        foreach ($changes as $field => [$old, $new]) {
            AuditLog::record('updated', self::ENTITY, $profile->id,
                'Updated LIPA record ('.self::lipaTitle($entry).'): '.self::LIPA_LABELS[$field], [
                    'field_name' => $field, 'old_value' => $old, 'new_value' => $new,
                ]);
        }
    }

    public static function lipaDeleted(IaProfile $profile, IaLipaEntry $entry): void
    {
        AuditLog::record('deleted', self::ENTITY, $profile->id, 'Deleted LIPA record: '.self::lipaTitle($entry));
    }

    private static function lipaTitle(IaLipaEntry $entry): string
    {
        return collect([
            'CY '.$entry->crop_year.' '.ucfirst((string) $entry->season),
            $entry->sector,
            $entry->lot_no ? 'Lot '.$entry->lot_no : null,
            $entry->landownerName() ?: null,
        ])->filter()->implode(' · ');
    }

    private static function lipaValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if (in_array($field, ['service_area_ha', 'irrigated_planted_area_ha'], true)) {
            return rtrim(rtrim((string) $value, '0'), '.') ?: '0';
        }

        return (string) $value;
    }

    /** Human-readable value: ids become names, dates become Y-m-d, numbers lose trailing zeros. */
    private static function display(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return match ($field) {
            'office_id' => Office::find($value)?->code ?? (string) $value,
            'province_id' => Province::find($value)?->name ?? (string) $value,
            'district_id' => CongressionalDistrict::find($value)?->name ?? (string) $value,
            'assigned_user_id' => User::find($value)?->name ?? (string) $value,
            'service_area_ha', 'fusa_ha', 'canal_length_km' => rtrim(rtrim((string) $value, '0'), '.') ?: '0',
            default => (string) $value,
        };
    }
}
