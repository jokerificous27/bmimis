<?php

namespace App\Enums;

enum Permission: string
{
    case ViewAllRecords = 'records.view_all';
    case ManagePersonnel = 'personnel.manage';
    case ImportPersonnel = 'personnel.import';
    case ExportRecords = 'records.export';
    case EncodeAssessments = 'assessments.encode';
    case CertifyAssessments = 'assessments.certify';
    case CorrectRecords = 'records.correct';
    case EncodeMonitoring = 'monitoring.encode';
    case ManageInterventions = 'interventions.manage';
    case ViewCompliance = 'compliance.view';
    case ViewAnalytics = 'analytics.view';
    case GenerateReports = 'reports.generate';
    case ManageUsers = 'users.manage';
    case ManageUnits = 'units.manage';
    case ManageRanks = 'ranks.manage';
    case ManageStandards = 'standards.manage';
    case ManagePackages = 'packages.manage';
    case ViewAuditTrail = 'audit.view';
    case ManageSettings = 'settings.manage';

    public function label(): string
    {
        return match ($this) {
            self::ViewAllRecords => 'View all personnel health records',
            self::ManagePersonnel => 'Register and edit personnel',
            self::ImportPersonnel => 'Import personnel and monthly weights',
            self::ExportRecords => 'Export records (Excel)',
            self::EncodeAssessments => 'Encode BMI assessments',
            self::CertifyAssessments => 'Certify BMI assessments',
            self::CorrectRecords => 'Correct submitted/certified records',
            self::EncodeMonitoring => 'Encode monthly weight monitoring',
            self::ManageInterventions => 'Manage interventions and follow-ups',
            self::ViewCompliance => 'View compliance monitoring',
            self::ViewAnalytics => 'View BMI analytics',
            self::GenerateReports => 'Generate and print reports',
            self::ManageUsers => 'Manage user accounts and permissions',
            self::ManageUnits => 'Manage units / offices',
            self::ManageRanks => 'Manage ranks',
            self::ManageStandards => 'Manage BMI standards',
            self::ManagePackages => 'Manage intervention packages',
            self::ViewAuditTrail => 'View audit trail',
            self::ManageSettings => 'Manage system settings',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ViewAllRecords, self::ManagePersonnel, self::ImportPersonnel, self::ExportRecords => 'Personnel & Records',
            self::EncodeAssessments, self::CertifyAssessments, self::CorrectRecords, self::EncodeMonitoring, self::ManageInterventions => 'Health Service Operations',
            self::ViewCompliance, self::ViewAnalytics, self::GenerateReports => 'Monitoring & Reports',
            default => 'Administration',
        };
    }
}
