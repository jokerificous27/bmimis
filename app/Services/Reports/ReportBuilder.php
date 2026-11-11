<?php

namespace App\Services\Reports;

use App\Enums\ClassificationCategory;
use App\Enums\InterventionStatus;
use App\Models\BmiAssessment;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use App\Models\PersonnelIntervention;
use App\Models\Unit;
use App\Models\User;
use App\Services\BmiStatistics;
use App\Services\ComplianceService;
use App\Services\MonthlyMonitoringService;
use App\Services\PersonnelQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Builds tabular report data from live records. Every query starts from the personnel the user may see.
 *
 * Report structure: title, subtitle, orientation, meta (filter labels), summary (label => value)
 * and sections (title, columns, rows).
 */
class ReportBuilder
{
    public function __construct(
        private BmiStatistics $statistics,
        private ComplianceService $compliance,
        private MonthlyMonitoringService $monitoring,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{key: string, title: string, subtitle: string|null, orientation: string, meta: array<string, string>, summary: array<string, string|int|float>, sections: list<array{title: string|null, columns: list<string>, rows: list<list<mixed>>}>}
     */
    public function build(string $key, User $user, array $filters): array
    {
        $definition = ReportCatalog::find($key) ?? throw new InvalidArgumentException("Unknown report [{$key}].");

        $filters = $this->normalize($filters);
        $personnel = PersonnelQuery::for($user, ['unit_id' => $filters['unit_id']]);

        $report = match ($key) {
            'individual-history' => $this->individualHistory($this->findPersonnel($user, $filters['personnel_id'])),
            'monthly-monitoring' => $this->monthlyMonitoring($personnel, $filters),
            'pending-monitoring' => $this->pendingMonitoring($personnel, $filters),
            'annual-monitoring' => $this->annualMonitoring($personnel, $filters),
            'weight-progress' => $this->weightProgress($personnel, $filters),
            'unit-compliance' => $this->unitCompliance($user, $filters),
            'summary' => $this->summary($personnel, $filters),
            'classification-distribution' => $this->classificationDistribution($personnel, $filters),
            'intervention' => $this->intervention($personnel),
            'personnel-directory' => $this->directory($personnel, $filters),
            default => throw new InvalidArgumentException("Report [{$key}] is a printable form."),
        };

        return array_merge([
            'key' => $key,
            'title' => $definition['title'],
            'subtitle' => null,
            'orientation' => $definition['orientation'],
            'meta' => $this->metaFor($definition['filters'], $filters),
            'summary' => [],
        ], $report);
    }

    public function findPersonnel(User $user, mixed $personnelId): Personnel
    {
        return Personnel::query()->visibleTo($user)->with(['rank', 'unit'])->findOrFail((int) $personnelId);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalize(array $filters): array
    {
        $today = CarbonImmutable::today();

        return [
            'year' => (int) ($filters['year'] ?? $today->year) ?: $today->year,
            'month' => min(12, max(1, (int) ($filters['month'] ?? $today->month) ?: $today->month)),
            'unit_id' => filled($filters['unit_id'] ?? null) ? (int) $filters['unit_id'] : null,
            'category' => ClassificationCategory::tryFrom((string) ($filters['category'] ?? ''))?->value,
            'personnel_id' => filled($filters['personnel_id'] ?? null) ? (int) $filters['personnel_id'] : null,
            'assessment_id' => filled($filters['assessment_id'] ?? null) ? (int) $filters['assessment_id'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function individualHistory(Personnel $person): array
    {
        $assessments = $person->assessments()->official()->with(['encoder:id,name', 'interventionPackage:id,code'])->orderByDesc('assessed_on')->get();
        $monthly = $person->monthlyWeights()->orderByDesc('year')->orderByDesc('month')->get();

        return [
            'subtitle' => $person->rank_name.' — '.$person->employee_number.' — '.($person->unit?->name ?? 'No unit'),
            'summary' => [
                'Assessments' => $assessments->count(),
                'Monthly records' => $monthly->count(),
                'Latest BMI' => $assessments->first() ? number_format($assessments->first()->bmi, 1) : '—',
                'Latest classification' => $assessments->first()?->pnp_classification ?? '—',
            ],
            'sections' => [
                [
                    'title' => 'BMI Assessments',
                    'columns' => ['Date', 'Height (cm)', 'Weight (kg)', 'Waist', 'Hip', 'Wrist', 'BMI', 'PNP', 'WHO', 'IBW', 'Weight to lose', 'Package', 'Status', 'Encoded by'],
                    'rows' => $assessments->map(fn (BmiAssessment $a) => [
                        $a->assessed_on->format('Y-m-d'), $a->height_cm, $a->weight_kg, $a->waist_cm, $a->hip_cm, $a->wrist_cm,
                        round($a->bmi, 1), $a->pnp_classification, $a->who_classification, $a->ibwRangeText(), $a->weightActionText(),
                        $a->interventionPackage?->code, $a->status->label(), $a->encoder?->name,
                    ])->all(),
                ],
                [
                    'title' => 'Monthly Weight Monitoring',
                    'columns' => ['Year', 'Month', 'Weight (kg)', 'Height used (cm)', 'BMI', 'PNP classification', 'Measured on'],
                    'rows' => $monthly->map(fn (MonthlyWeightMonitoring $m) => [
                        $m->year, $m->monthName(), $m->weight_kg, $m->height_cm, $m->bmi !== null ? round($m->bmi, 1) : null, $m->pnp_classification, $m->measured_on?->format('Y-m-d'),
                    ])->all(),
                ],
            ],
        ];
    }

    /**
     * @param  Builder<Personnel>  $personnel
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function monthlyMonitoring(Builder $personnel, array $filters): array
    {
        ['year' => $year, 'month' => $month] = $filters;
        $previous = CarbonImmutable::create($year, $month, 1)->subMonth();

        $people = $this->withCategory((clone $personnel)->active(), $filters, $year, $month)
            ->with(['rank:id,abbreviation', 'unit:id,code',
                'monthlyWeights' => fn ($query) => $query->where(fn ($query) => $query
                    ->where(fn ($q) => $q->where('year', $year)->where('month', $month))
                    ->orWhere(fn ($q) => $q->where('year', $previous->year)->where('month', $previous->month))),
            ])
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $rows = $people->map(function (Personnel $person) use ($year, $month, $previous) {
            $current = $person->monthlyWeights->first(fn ($m) => $m->year === $year && $m->month === $month);
            $prior = $person->monthlyWeights->first(fn ($m) => $m->year === $previous->year && $m->month === $previous->month);

            return [
                $person->rank_name, $person->unit?->code, $current?->weight_kg, $current?->height_cm,
                $current?->bmi !== null ? round($current->bmi, 1) : null, $current?->pnp_classification, $current?->who_classification,
                $current && $prior ? round($current->weight_kg - $prior->weight_kg, 2) : null,
                $current?->measured_on?->format('Y-m-d'),
                $current ? 'Recorded' : ucfirst($this->monitoring->periodStatus($year, $month)),
            ];
        });

        $recorded = $rows->filter(fn ($row) => $row[9] === 'Recorded')->count();

        return [
            'subtitle' => MonthlyWeightMonitoring::MONTHS[$month].' '.$year,
            'summary' => [
                'Personnel' => $rows->count(),
                'Recorded' => $recorded,
                'Not recorded' => $rows->count() - $recorded,
                'Compliance' => $rows->count() ? round($recorded / $rows->count() * 100, 1).'%' : '0%',
            ],
            'sections' => [[
                'title' => null,
                'columns' => ['Rank / Name', 'Unit', 'Weight (kg)', 'Height (cm)', 'BMI', 'PNP', 'WHO', 'Change (kg)', 'Measured on', 'Status'],
                'rows' => $rows->all(),
            ]],
        ];
    }

    /**
     * @param  Builder<Personnel>  $personnel
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function pendingMonitoring(Builder $personnel, array $filters): array
    {
        ['year' => $year, 'month' => $month] = $filters;

        $missing = $this->compliance->missingQuery($personnel, $year, $month)
            ->with(['rank:id,abbreviation', 'unit:id,code'])
            ->addSelect(['last_year' => MonthlyWeightMonitoring::select('year')->whereColumn('personnel_id', 'personnel.id')->orderByDesc('year')->orderByDesc('month')->limit(1)])
            ->addSelect(['last_month' => MonthlyWeightMonitoring::select('month')->whereColumn('personnel_id', 'personnel.id')->orderByDesc('year')->orderByDesc('month')->limit(1)])
            ->addSelect(['last_weight' => MonthlyWeightMonitoring::select('weight_kg')->whereColumn('personnel_id', 'personnel.id')->orderByDesc('year')->orderByDesc('month')->limit(1)])
            ->orderBy('unit_id')->orderBy('last_name')
            ->get();

        $summary = $this->compliance->summary($personnel, $year, $month);

        return [
            'subtitle' => MonthlyWeightMonitoring::MONTHS[$month].' '.$year.' — deadline '.$this->monitoring->deadlineFor($year, $month)->format('F j, Y'),
            'summary' => ['Active personnel' => $summary['total'], 'Pending' => $summary['pending'], 'Compliance' => $summary['percent'].'%'],
            'sections' => [[
                'title' => null,
                'columns' => ['#', 'Rank / Name', 'Employee No.', 'Unit', 'Last recorded', 'Last weight (kg)', 'Contact'],
                'rows' => $missing->values()->map(fn (Personnel $p, int $i) => [
                    $i + 1, $p->rank_name, $p->employee_number, $p->unit?->code,
                    $p->last_year ? MonthlyWeightMonitoring::MONTHS[(int) $p->last_month].' '.$p->last_year : 'Never',
                    $p->last_weight !== null ? (float) $p->last_weight : null, $p->contact_number,
                ])->all(),
            ]],
        ];
    }

    /**
     * @param  Builder<Personnel>  $personnel
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function annualMonitoring(Builder $personnel, array $filters): array
    {
        $year = $filters['year'];

        $people = $this->withCategory((clone $personnel), $filters, $year, 12)
            ->where(fn (Builder $query) => $query->active()->orWhereHas('monthlyWeights', fn ($query) => $query->where('year', $year)))
            ->with(['rank:id,abbreviation', 'unit:id,code', 'monthlyWeights' => fn ($query) => $query->where('year', $year)])
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $rows = $people->map(function (Personnel $person) {
            $weights = $person->monthlyWeights->keyBy('month');
            $recorded = $person->monthlyWeights->sortBy('month');

            return array_merge(
                [$person->rank_name, $person->unit?->code],
                array_map(fn (int $m) => $weights->get($m)?->weight_kg, range(1, 12)),
                [$recorded->count(), $recorded->count() > 1 ? round($recorded->last()->weight_kg - $recorded->first()->weight_kg, 2) : null],
            );
        });

        return [
            'subtitle' => 'Calendar year '.$year,
            'summary' => ['Personnel' => $rows->count(), 'Monthly records' => $people->sum(fn ($p) => $p->monthlyWeights->count())],
            'sections' => [[
                'title' => 'Weight (kg)',
                'columns' => array_merge(['Rank / Name', 'Unit'], ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], ['Months', 'Change']),
                'rows' => $rows->all(),
            ]],
        ];
    }

    /**
     * @param  Builder<Personnel>  $personnel
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function weightProgress(Builder $personnel, array $filters): array
    {
        $year = $filters['year'];

        $people = $this->withCategory((clone $personnel)->active(), $filters, $year, 12)
            ->with(['rank:id,abbreviation', 'unit:id,code', 'monthlyWeights' => fn ($query) => $query->where('year', $year)->orderBy('month')])
            ->orderBy('last_name')
            ->get()
            ->filter(fn (Personnel $person) => $person->monthlyWeights->isNotEmpty());

        $rows = $people->map(function (Personnel $person) {
            $first = $person->monthlyWeights->first();
            $last = $person->monthlyWeights->last();
            $change = round($last->weight_kg - $first->weight_kg, 2);

            return [
                $person->rank_name, $person->unit?->code,
                $first->monthName().' — '.number_format($first->weight_kg, 2),
                $last->monthName().' — '.number_format($last->weight_kg, 2),
                $change,
                $change > 0 ? 'Increased' : ($change < 0 ? 'Decreased' : 'No change'),
                $last->pnp_classification,
            ];
        })->values();

        return [
            'subtitle' => 'Calendar year '.$year,
            'summary' => [
                'Personnel' => $rows->count(),
                'Weight decreased' => $rows->where(5, 'Decreased')->count(),
                'Weight increased' => $rows->where(5, 'Increased')->count(),
                'Average change (kg)' => $rows->isNotEmpty() ? round($rows->avg(4), 2) : 0,
            ],
            'sections' => [[
                'title' => null,
                'columns' => ['Rank / Name', 'Unit', 'First weigh-in', 'Latest weigh-in', 'Change (kg)', 'Trend', 'Latest PNP classification'],
                'rows' => $rows->all(),
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function unitCompliance(User $user, array $filters): array
    {
        ['year' => $year, 'month' => $month] = $filters;
        $units = $this->compliance->byUnit($user, $year, $month, $filters['unit_id'])->sortBy(fn ($row) => $row['unit']->name)->values();
        $total = $units->sum('total');
        $recorded = $units->sum('recorded');

        return [
            'subtitle' => MonthlyWeightMonitoring::MONTHS[$month].' '.$year,
            'summary' => [
                'Units' => $units->count(),
                'Personnel' => $total,
                'Recorded' => $recorded,
                'Overall compliance' => $total ? round($recorded / $total * 100, 1).'%' : '0%',
                'Units meeting target' => $units->where('meets_target', true)->count(),
            ],
            'sections' => [[
                'title' => null,
                'columns' => ['Unit / Office', 'Code', 'Active personnel', 'Recorded', 'Missing', 'Compliance %', 'Status'],
                'rows' => $units->map(fn ($row) => [
                    $row['unit']->name, $row['unit']->code, $row['total'], $row['recorded'], $row['missing'], $row['percent'],
                    $row['meets_target'] ? 'Meets target' : 'Below target',
                ])->all(),
            ]],
        ];
    }

    /**
     * @param  Builder<Personnel>  $personnel
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function summary(Builder $personnel, array $filters): array
    {
        $data = $this->statistics->dashboard($personnel, $filters['year'], $filters['month']);
        $stats = $data['stats'];

        return [
            'subtitle' => 'As of '.MonthlyWeightMonitoring::MONTHS[$filters['month']].' '.$filters['year'],
            'summary' => [
                'Registered personnel' => $stats['total'],
                'With BMI records' => $stats['with_records'],
                'Normal' => $stats['normal'],
                'Below normal' => $stats['below'],
                'Above normal' => $stats['above'],
                'Requiring intervention' => $stats['requiring_intervention'],
                'Monthly compliance' => $stats['compliance_percent'].'%',
                'Average BMI' => $stats['average_bmi'] ?? '—',
            ],
            'sections' => [
                [
                    'title' => 'PNP Classification (latest assessment)',
                    'columns' => ['Classification', 'Personnel', '%'],
                    'rows' => $this->distributionRows($data['charts']['pnp_distribution']),
                ],
                [
                    'title' => 'BMI Status by Unit',
                    'columns' => ['Unit', 'Below normal', 'Normal', 'Above normal', 'No assessment'],
                    'rows' => collect($data['charts']['unit_distribution'])->map(fn ($counts, $unit) => [$unit, $counts['below'], $counts['normal'], $counts['above'], $counts['none']])->values()->all(),
                ],
                [
                    'title' => 'Monthly Compliance '.$filters['year'],
                    'columns' => ['Month', 'Compliance %', 'Average weight (kg)', 'Average BMI'],
                    'rows' => collect($data['charts']['compliance_trend'])->map(fn ($percent, $month) => [
                        MonthlyWeightMonitoring::MONTHS[$month], $percent, $data['charts']['monthly_averages'][$month]['weight'], $data['charts']['monthly_averages'][$month]['bmi'],
                    ])->values()->all(),
                ],
                [
                    'title' => 'Ongoing Interventions by Package',
                    'columns' => ['Package', 'Personnel'],
                    'rows' => collect($data['charts']['intervention_distribution'])->map(fn ($count, $package) => [$package, $count])->values()->all(),
                ],
            ],
        ];
    }

    /**
     * @param  Builder<Personnel>  $personnel
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function classificationDistribution(Builder $personnel, array $filters): array
    {
        $data = $this->statistics->dashboard($personnel, $filters['year'], $filters['month']);

        return [
            'subtitle' => 'Latest assessment as of '.MonthlyWeightMonitoring::MONTHS[$filters['month']].' '.$filters['year'],
            'summary' => ['Personnel with assessment' => $data['stats']['with_records'], 'Without assessment' => $data['stats']['total'] - $data['stats']['with_records']],
            'sections' => [
                ['title' => 'PNP Acceptable BMI Classification', 'columns' => ['Classification', 'Personnel', '%'], 'rows' => $this->distributionRows($data['charts']['pnp_distribution'])],
                ['title' => 'WHO Standard Classification', 'columns' => ['Classification', 'Personnel', '%'], 'rows' => $this->distributionRows($data['charts']['who_distribution'])],
            ],
        ];
    }

    /**
     * @param  Builder<Personnel>  $personnel
     * @return array<string, mixed>
     */
    private function intervention(Builder $personnel): array
    {
        $latest = $this->statistics->latestAssessments((clone $personnel)->active(), today());
        $ongoing = PersonnelIntervention::query()->where('status', InterventionStatus::Ongoing->value)
            ->whereIn('personnel_id', (clone $personnel)->select('personnel.id'))
            ->with('package:id,code,name')
            ->get()
            ->keyBy('personnel_id');

        $ids = $latest->filter(fn (BmiAssessment $a) => $a->requires_intervention)->keys()->merge($ongoing->keys())->unique();

        $people = Personnel::query()->whereIn('id', $ids)->with(['rank:id,abbreviation', 'unit:id,code'])->orderBy('last_name')->get();

        return [
            'summary' => [
                'Personnel requiring intervention' => $people->count(),
                'With ongoing intervention' => $ongoing->count(),
                'Not yet assigned' => $people->count() - $people->filter(fn ($p) => $ongoing->has($p->id))->count(),
            ],
            'sections' => [[
                'title' => null,
                'columns' => ['Rank / Name', 'Unit', 'Assessment date', 'BMI', 'PNP classification', 'Weight to lose', 'Package', 'Assigned', 'Next follow-up'],
                'rows' => $people->map(function (Personnel $person) use ($latest, $ongoing) {
                    $assessment = $latest->get($person->id);
                    $intervention = $ongoing->get($person->id);

                    return [
                        $person->rank_name, $person->unit?->code, $assessment?->assessed_on?->format('Y-m-d'),
                        $assessment ? round($assessment->bmi, 1) : null, $assessment?->pnp_classification, $assessment?->weightActionText(),
                        $intervention?->package?->displayName() ?? 'Not yet assigned',
                        $intervention?->assigned_on?->format('Y-m-d'), $intervention?->next_follow_up_on?->format('Y-m-d'),
                    ];
                })->all(),
            ]],
        ];
    }

    /**
     * @param  Builder<Personnel>  $personnel
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function directory(Builder $personnel, array $filters): array
    {
        $people = $this->withCategory((clone $personnel), $filters, today()->year, today()->month)
            ->with(['rank:id,abbreviation', 'unit:id,code,name', 'latestAssessment'])
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        return [
            'summary' => ['Personnel' => $people->count()],
            'sections' => [[
                'title' => null,
                'columns' => ['Employee No.', 'Rank', 'Last name', 'First name', 'Middle name', 'Suffix', 'Sex', 'Date of birth', 'Age', 'Unit', 'Position', 'Contact', 'Status', 'Latest assessment', 'Height (cm)', 'Weight (kg)', 'BMI', 'PNP', 'WHO'],
                'rows' => $people->map(fn (Personnel $p) => [
                    $p->employee_number, $p->rank?->abbreviation, $p->last_name, $p->first_name, $p->middle_name, $p->suffix,
                    $p->sex->label(), $p->date_of_birth?->format('Y-m-d'), $p->age, $p->unit?->code, $p->position, $p->contact_number, $p->status->label(),
                    $p->latestAssessment?->assessed_on?->format('Y-m-d'), $p->latestAssessment?->height_cm, $p->latestAssessment?->weight_kg,
                    $p->latestAssessment ? round($p->latestAssessment->bmi, 1) : null, $p->latestAssessment?->pnp_classification, $p->latestAssessment?->who_classification,
                ])->all(),
            ]],
        ];
    }

    /**
     * Limit personnel to a PNP classification category based on their latest assessment up to the period.
     *
     * @param  Builder<Personnel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Personnel>
     */
    private function withCategory(Builder $query, array $filters, int $year, int $month): Builder
    {
        if (! $filters['category']) {
            return $query;
        }

        $asOf = CarbonImmutable::create($year, $month, 1)->endOfMonth()->min(today());
        $latest = $this->statistics->latestAssessments(clone $query, $asOf)
            ->filter(fn (BmiAssessment $assessment) => $assessment->pnp_category?->value === $filters['category']);

        return $query->whereIn('personnel.id', $latest->keys());
    }

    /**
     * @param  array<string, int>  $distribution
     * @return list<list<mixed>>
     */
    private function distributionRows(array $distribution): array
    {
        $total = array_sum($distribution);

        return collect($distribution)->map(fn (int $count, string $label) => [$label, $count, $total ? round($count / $total * 100, 1) : 0])->values()->all();
    }

    /**
     * @param  list<string>  $filterKeys
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function metaFor(array $filterKeys, array $filters): array
    {
        $meta = [];

        if (in_array('year', $filterKeys, true)) {
            $meta['Year'] = (string) $filters['year'];
        }

        if (in_array('month', $filterKeys, true)) {
            $meta['Month'] = MonthlyWeightMonitoring::MONTHS[$filters['month']];
        }

        if (in_array('unit_id', $filterKeys, true)) {
            $meta['Unit / Office'] = $filters['unit_id'] ? (Unit::withTrashed()->find($filters['unit_id'])?->name ?? '—') : 'All authorized units';
        }

        if (in_array('category', $filterKeys, true) && $filters['category']) {
            $meta['Classification'] = ClassificationCategory::from($filters['category'])->label();
        }

        return $meta;
    }

    /**
     * Rows shaped for an Excel worksheet per section.
     *
     * @param  array<string, mixed>  $report
     * @return Collection<int, array{name: string, title: string, columns: list<string>, rows: list<list<mixed>>}>
     */
    public function sheets(array $report): Collection
    {
        return collect($report['sections'])->values()->map(fn (array $section, int $index) => [
            'name' => $section['title'] ?? ($index === 0 ? 'Report' : 'Section '.($index + 1)),
            'title' => $report['title'].($report['subtitle'] ? ' — '.$report['subtitle'] : '').($section['title'] ? ' — '.$section['title'] : ''),
            'columns' => $section['columns'],
            'rows' => $section['rows'],
        ]);
    }
}
