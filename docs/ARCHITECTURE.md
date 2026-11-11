# System Architecture

## Layers

| Layer | Location | Responsibility |
|---|---|---|
| Enums | `app/Enums` | Roles, permissions, statuses, classification categories, IBW methods |
| Models | `app/Models` | Eloquent models; `Concerns/TracksAuthors` (created_by/updated_by) and `Concerns/RecordsActivity` (automatic audit entries) |
| BMI engine | `app/Services/Bmi` | `BmiCalculator` (pure math), `BmiClassifier` (standard in force on a date), `BmiEvaluator` (single entry point used everywhere) |
| Domain services | `app/Services` | `AssessmentService`, `MonthlyMonitoringService`, `InterventionService`, `ComplianceService`, `BmiStatistics`, `HealthAlerts`, `PersonnelQuery` |
| Reports | `app/Services/Reports` | `ReportCatalog` (definitions) and `ReportBuilder` (tabular data for print and Excel) |
| Import/Export | `app/Services/Import`, `app/Support/Spreadsheet` | Validated XLSX/CSV import; dependency-free XLSX writer |
| Authorization | `app/Policies`, `app/Http/Middleware` | Policies per resource, `permission:` route middleware, active-account guard |
| UI | `app/Livewire`, `resources/views/livewire` | Full-page Livewire components grouped by module |
| Print | `app/Http/Controllers/PrintController`, `resources/views/print` | A4 official forms and reports |

## Data model

- `users` — login accounts with `role`, optional `unit_id` (unit scope) and `is_active`.
- `units`, `ranks` — reference data (soft-deleted units keep historical references).
- `personnel` — personal information; `user_id` links the personnel account. Unique `employee_number`.
- `personnel_assignments` — history of unit / rank / position (transfers and promotions).
- `bmi_standards` + `bmi_classifications` — versioned WHO and PNP standards with effective dates; bands support optional age/sex limits.
- `bmi_assessments` — the digital BMI Form. Stores measurements **and** a snapshot of computed results (BMI, IBW, weight to lose, PNP/WHO labels and categories, unit/rank/age at the time). Status: draft → submitted → certified, or superseded.
- `monthly_weight_monitorings` — one row per personnel per month (`UNIQUE(personnel_id, year, month)`), stores the height used and the computed BMI/classifications.
- `intervention_packages`, `personnel_interventions`, `intervention_follow_ups`.
- `activity_logs` — append-only audit trail (model blocks update/delete).
- `system_settings`, `role_permissions`, `notifications` (Laravel database notifications).

## Key rules

- **Historical reproducibility:** classification results are snapshots; changing standards or settings never rewrites existing records. Monthly records store the height they were computed with.
- **No silent overwrites:** corrections of submitted/certified assessments and monthly entries require a reason, which is written with old/new values to the audit trail. A same-day assessment supersedes (not replaces) the earlier one after confirmation. A corrected certified assessment must be certified again.
- **Segregation of duties:** an encoder cannot certify an assessment they encoded (Super Administrator excepted).
- **Data scope:** `Personnel::scopeVisibleTo()` and `User::canAccessPersonnel()` are the single source of truth — all records (`records.view_all`), own unit (unit representatives or staff without `records.view_all`), or own record only. Every list, report, export, print view and photo goes through them; hiding menu items is never relied upon.

## Roles and default permissions

Permissions are editable per role in *Roles & Permissions* (Super Administrator always has all).

| Permission | HS Admin | Encoder | Unit Rep | Personnel |
|---|:-:|:-:|:-:|:-:|
| View all records | ✓ | ✓ | | |
| Register/edit personnel, import | ✓ | | | |
| Encode assessments / monthly weights | ✓ | ✓ | | |
| Certify assessments, correct records | ✓ | | | |
| Manage interventions | ✓ | | | |
| Compliance, reports | ✓ | ✓ | ✓ (own unit) | |
| Analytics, export, standards, packages, units, ranks, audit | ✓ | | | |
| Users, settings | Super Admin only by default | | | |

Personnel accounts always see their own profile, monitoring, assessments and notifications.

## Scheduling and notifications

`bmi:send-reminders` (daily 07:00) sends *Monthly monitoring due* to personnel without a weight on the reminder day (deadline minus reminder days), compliance reminders to unit representatives and Health Service administrators, and follow-up reminders the day before a scheduled follow-up. Other notifications are sent on events (assessment available/certified, weight recorded, intervention assigned, follow-up scheduled). All use the `database` channel; the `mail` channel is added when *email notifications* is enabled in settings.

## Tests

`tests/Unit/BmiCalculatorTest.php` (formula, rounding, IBW methods, weight to lose), and feature suites for classification, personnel access isolation, monthly monitoring, assessments/certification/corrections, reports and statistics, administration (permissions, audit immutability, import, reminders, standards validation) plus a smoke test rendering every page and printable report.
