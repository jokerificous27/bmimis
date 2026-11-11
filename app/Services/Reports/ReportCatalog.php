<?php

namespace App\Services\Reports;

/**
 * The reports available in the Reports module, grouped as in the sidebar.
 */
class ReportCatalog
{
    /**
     * @var array<string, array{title: string, category: string, description: string, orientation: string, filters: list<string>, requires: list<string>}>
     */
    public const REPORTS = [
        'bmi-form' => [
            'title' => 'Individual BMI Form',
            'category' => 'individual',
            'description' => 'Official PNP Health Service Body Mass Index (BMI) Form for one assessment.',
            'orientation' => 'portrait',
            'filters' => ['personnel_id'],
            'requires' => ['personnel_id'],
        ],
        'monitoring-form' => [
            'title' => 'Individual BMI Monitoring Form',
            'category' => 'individual',
            'description' => 'Personnel BMI monitoring profile with the January–December weight table.',
            'orientation' => 'landscape',
            'filters' => ['personnel_id', 'year'],
            'requires' => ['personnel_id'],
        ],
        'personnel-profile' => [
            'title' => 'Consolidated BMI Profile',
            'category' => 'individual',
            'description' => 'Printable personnel profile: personal data, current BMI, assessments, monitoring and interventions.',
            'orientation' => 'portrait',
            'filters' => ['personnel_id', 'year'],
            'requires' => ['personnel_id'],
        ],
        'individual-history' => [
            'title' => 'Individual BMI History',
            'category' => 'individual',
            'description' => 'Every recorded BMI assessment and monthly weight of one personnel.',
            'orientation' => 'landscape',
            'filters' => ['personnel_id'],
            'requires' => ['personnel_id'],
        ],
        'monthly-monitoring' => [
            'title' => 'Monthly Weight Monitoring Report',
            'category' => 'monthly',
            'description' => 'Weights recorded for a month with BMI, classification and change from the previous month.',
            'orientation' => 'landscape',
            'filters' => ['year', 'month', 'unit_id', 'category'],
            'requires' => [],
        ],
        'pending-monitoring' => [
            'title' => 'Personnel with Pending Monthly Monitoring',
            'category' => 'monthly',
            'description' => 'Active personnel without a recorded weight for the month.',
            'orientation' => 'portrait',
            'filters' => ['year', 'month', 'unit_id'],
            'requires' => [],
        ],
        'annual-monitoring' => [
            'title' => 'Annual BMI Monitoring Report',
            'category' => 'annual',
            'description' => 'January–December weights of each personnel for a year.',
            'orientation' => 'landscape',
            'filters' => ['year', 'unit_id', 'category'],
            'requires' => [],
        ],
        'weight-progress' => [
            'title' => 'Personnel Weight Progress Report',
            'category' => 'annual',
            'description' => 'First and latest weight of the year, total change and trend for each personnel.',
            'orientation' => 'portrait',
            'filters' => ['year', 'unit_id', 'category'],
            'requires' => [],
        ],
        'unit-compliance' => [
            'title' => 'Unit BMI Compliance Report',
            'category' => 'unit',
            'description' => 'Monthly monitoring compliance of each unit / office.',
            'orientation' => 'portrait',
            'filters' => ['year', 'month', 'unit_id'],
            'requires' => [],
        ],
        'summary' => [
            'title' => 'Overall Health Service BMI Summary',
            'category' => 'summary',
            'description' => 'Population BMI status, compliance and interventions at a glance.',
            'orientation' => 'portrait',
            'filters' => ['year', 'month', 'unit_id'],
            'requires' => [],
        ],
        'classification-distribution' => [
            'title' => 'BMI Classification Distribution',
            'category' => 'summary',
            'description' => 'Count of personnel per PNP and WHO classification based on the latest assessment.',
            'orientation' => 'portrait',
            'filters' => ['year', 'month', 'unit_id'],
            'requires' => [],
        ],
        'intervention' => [
            'title' => 'Personnel Requiring Intervention',
            'category' => 'summary',
            'description' => 'Personnel whose latest classification requires intervention, with package and follow-up status.',
            'orientation' => 'landscape',
            'filters' => ['unit_id'],
            'requires' => [],
        ],
        'personnel-directory' => [
            'title' => 'Personnel Masterlist',
            'category' => 'summary',
            'description' => 'Personnel directory with latest BMI result (also used as the Excel personnel export).',
            'orientation' => 'landscape',
            'filters' => ['unit_id', 'category'],
            'requires' => [],
        ],
    ];

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'individual' => __('Individual Reports'),
            'monthly' => __('Monthly Reports'),
            'annual' => __('Annual Reports'),
            'unit' => __('Unit Reports'),
            'summary' => __('Summary Reports'),
        ];
    }

    public static function categoryIcon(string $category): string
    {
        return match ($category) {
            'individual' => 'document-text',
            'monthly' => 'calendar-days',
            'annual' => 'calendar',
            'unit' => 'building-office-2',
            default => 'presentation-chart-bar',
        };
    }

    /**
     * @return array<string, array{title: string, category: string, description: string, orientation: string, filters: list<string>, requires: list<string>}>
     */
    public static function inCategory(string $category): array
    {
        return array_filter(self::REPORTS, fn (array $report) => $report['category'] === $category);
    }

    /**
     * @return array{title: string, category: string, description: string, orientation: string, filters: list<string>, requires: list<string>}|null
     */
    public static function find(string $key): ?array
    {
        return self::REPORTS[$key] ?? null;
    }

    /**
     * Reports rendered as official forms by the print controller instead of the tabular report layout.
     */
    public static function isForm(string $key): bool
    {
        return in_array($key, ['bmi-form', 'monitoring-form', 'personnel-profile'], true);
    }
}
