<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case HealthServiceAdmin = 'health_service_admin';
    case HealthServiceEncoder = 'health_service_encoder';
    case UnitRepresentative = 'unit_representative';
    case Personnel = 'personnel';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::HealthServiceAdmin => 'Health Service Administrator',
            self::HealthServiceEncoder => 'Health Service Encoder',
            self::UnitRepresentative => 'Unit Representative',
            self::Personnel => 'PNP Personnel',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'red',
            self::HealthServiceAdmin => 'blue',
            self::HealthServiceEncoder => 'sky',
            self::UnitRepresentative => 'amber',
            self::Personnel => 'zinc',
        };
    }

    /**
     * Whether this role belongs to Health Service staff (administrative interface).
     */
    public function isStaff(): bool
    {
        return in_array($this, [self::SuperAdmin, self::HealthServiceAdmin, self::HealthServiceEncoder], true);
    }

    /**
     * Permissions granted to the role by default, before any administrator customization.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::HealthServiceAdmin => [
                Permission::ViewAllRecords,
                Permission::ManagePersonnel,
                Permission::ImportPersonnel,
                Permission::ExportRecords,
                Permission::EncodeAssessments,
                Permission::CertifyAssessments,
                Permission::CorrectRecords,
                Permission::EncodeMonitoring,
                Permission::ManageInterventions,
                Permission::ViewCompliance,
                Permission::ViewAnalytics,
                Permission::GenerateReports,
                Permission::ManagePackages,
                Permission::ManageStandards,
                Permission::ManageUnits,
                Permission::ManageRanks,
                Permission::ViewAuditTrail,
            ],
            self::HealthServiceEncoder => [
                Permission::ViewAllRecords,
                Permission::EncodeAssessments,
                Permission::EncodeMonitoring,
                Permission::ViewCompliance,
                Permission::GenerateReports,
            ],
            self::UnitRepresentative => [
                Permission::ViewCompliance,
                Permission::GenerateReports,
            ],
            self::Personnel => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $role) => [$role->value => $role->label()])->all();
    }
}
