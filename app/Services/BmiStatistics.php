<?php

namespace App\Services;

use App\Enums\ClassificationCategory;
use App\Enums\InterventionStatus;
use App\Models\BmiAssessment;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use App\Models\PersonnelIntervention;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Aggregated BMI statistics computed from live records for dashboards, analytics and summary reports.
 */
class BmiStatistics
{
    /**
     * Latest official assessment for each personnel as of a date, keyed by personnel id.
     *
     * @param  Builder<Personnel>  $personnel
     * @return Collection<int, BmiAssessment>
     */
    public function latestAssessments(Builder $personnel, CarbonInterface $asOf): Collection
    {
        $latestIds = (clone $personnel)
            ->select('personnel.id')
            ->addSelect(['latest_assessment_id' => BmiAssessment::query()
                ->select('id')
                ->whereColumn('personnel_id', 'personnel.id')
                ->official()
                ->whereDate('assessed_on', '<=', $asOf)
                ->orderByDesc('assessed_on')
                ->orderByDesc('id')
                ->limit(1),
            ])
            ->toBase()
            ->pluck('latest_assessment_id')
            ->filter()
            ->values();

        if ($latestIds->isEmpty()) {
            return collect();
        }

        return $latestIds->chunk(1000)->flatMap(fn (Collection $chunk) => BmiAssessment::query()
            ->whereIn('id', $chunk)
            ->get(['id', 'personnel_id', 'assessed_on', 'bmi', 'weight_kg', 'height_cm', 'pnp_classification', 'pnp_category', 'who_classification', 'who_category', 'requires_intervention', 'intervention_package_id']))
            ->keyBy('personnel_id');
    }

    /**
     * Full dashboard data set for a filtered population and period.
     *
     * @param  Builder<Personnel>  $personnel  authorized and filtered personnel query
     * @return array<string, mixed>
     */
    public function dashboard(Builder $personnel, int $year, int $month, ?string $category = null, ?string $complianceStatus = null): array
    {
        $periodEnd = CarbonImmutable::create($year, $month, 1)->endOfMonth();
        $asOf = $periodEnd->isAfter(today()) ? today() : $periodEnd;

        $rows = (clone $personnel)->active()
            ->withExists(['monthlyWeights as has_monthly' => fn (Builder $query) => $query->where('year', $year)->where('month', $month)])
            ->get(['personnel.id', 'personnel.unit_id', 'personnel.rank_id', 'personnel.sex']);

        $latest = $this->latestAssessments((clone $personnel)->active(), $asOf);

        $rows = $rows->filter(function (Personnel $row) use ($latest, $category, $complianceStatus): bool {
            $assessment = $latest->get($row->id);

            if ($category === 'none' && $assessment !== null) {
                return false;
            }

            if ($category && $category !== 'none' && $assessment?->pnp_category?->value !== $category) {
                return false;
            }

            if ($complianceStatus === 'complied' && ! $row->has_monthly) {
                return false;
            }

            return ! ($complianceStatus === 'pending' && $row->has_monthly);
        })->values();

        $ids = $rows->pluck('id');
        $latest = $latest->only($ids->all());

        $ongoingInterventions = PersonnelIntervention::query()
            ->where('status', InterventionStatus::Ongoing->value)
            ->whereIn('personnel_id', $ids)
            ->with('package:id,code,name')
            ->get(['id', 'personnel_id', 'intervention_package_id', 'next_follow_up_on']);

        $requiringIntervention = $latest->filter(fn (BmiAssessment $assessment) => $assessment->requires_intervention)->keys()
            ->merge($ongoingInterventions->pluck('personnel_id'))
            ->unique();

        $recorded = $rows->where('has_monthly', true)->count();
        $total = $rows->count();

        return [
            'stats' => [
                'total' => $total,
                'with_records' => $latest->count(),
                'normal' => $latest->filter(fn ($a) => $a->pnp_category === ClassificationCategory::Normal)->count(),
                'below' => $latest->filter(fn ($a) => $a->pnp_category === ClassificationCategory::Below)->count(),
                'above' => $latest->filter(fn ($a) => $a->pnp_category === ClassificationCategory::Above)->count(),
                'unclassified' => $latest->filter(fn ($a) => $a->pnp_category === null)->count(),
                'requiring_intervention' => $requiringIntervention->count(),
                'monthly_completed' => $recorded,
                'monthly_pending' => $total - $recorded,
                'compliance_percent' => $total > 0 ? round($recorded / $total * 100, 1) : 0.0,
                'average_bmi' => $latest->isNotEmpty() ? round((float) $latest->avg('bmi'), 1) : null,
            ],
            'charts' => [
                'pnp_distribution' => $this->distribution($latest, 'pnp_classification'),
                'who_distribution' => $this->distribution($latest, 'who_classification'),
                'compliance_trend' => $this->complianceTrend($ids, $year),
                'monthly_averages' => $this->monthlyAverages($ids, $year),
                'previous_year_averages' => $this->monthlyAverages($ids, $year - 1),
                'unit_distribution' => $this->unitDistribution($rows, $latest),
                'intervention_distribution' => $ongoingInterventions->groupBy(fn ($i) => $i->package?->displayName() ?? 'Unknown')->map->count()->sortDesc()->all(),
            ],
            'follow_ups' => $ongoingInterventions
                ->filter(fn ($i) => $i->next_follow_up_on && $i->next_follow_up_on->lte(today()->addDays(14)))
                ->sortBy('next_follow_up_on')
                ->values(),
            'personnel_ids' => $ids,
        ];
    }

    /**
     * Year-over-year comparison of BMI results.
     *
     * @param  Builder<Personnel>  $personnel
     * @param  list<int>  $years
     * @return array<int, array<string, mixed>>
     */
    public function annualComparison(Builder $personnel, array $years): array
    {
        $ids = (clone $personnel)->pluck('id');

        return collect($years)->mapWithKeys(function (int $year) use ($ids) {
            $assessments = BmiAssessment::query()->official()
                ->whereIn('personnel_id', $ids)
                ->whereYear('assessed_on', $year)
                ->get(['personnel_id', 'assessed_on', 'bmi', 'pnp_category'])
                ->sortByDesc('assessed_on')
                ->unique('personnel_id');

            $monthly = MonthlyWeightMonitoring::query()->whereIn('personnel_id', $ids)->where('year', $year);

            return [$year => [
                'assessed' => $assessments->count(),
                'average_bmi' => $assessments->isNotEmpty() ? round((float) $assessments->avg('bmi'), 1) : null,
                'normal' => $assessments->where('pnp_category', ClassificationCategory::Normal)->count(),
                'below' => $assessments->where('pnp_category', ClassificationCategory::Below)->count(),
                'above' => $assessments->where('pnp_category', ClassificationCategory::Above)->count(),
                'monthly_records' => (clone $monthly)->count(),
                'average_weight' => ($avg = (clone $monthly)->avg('weight_kg')) !== null ? round((float) $avg, 1) : null,
            ]];
        })->all();
    }

    /**
     * @param  Collection<int, BmiAssessment>  $latest
     * @return array<string, int>
     */
    private function distribution(Collection $latest, string $column): array
    {
        return $latest->groupBy(fn (BmiAssessment $assessment) => $assessment->{$column} ?? 'Unclassified')
            ->map->count()
            ->sortDesc()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return array<int, float|null>
     */
    private function complianceTrend(Collection $ids, int $year): array
    {
        $total = $ids->count();
        $counts = $total === 0 ? collect() : MonthlyWeightMonitoring::query()
            ->where('year', $year)->whereIn('personnel_id', $ids)
            ->selectRaw('month, count(*) as aggregate')->groupBy('month')
            ->pluck('aggregate', 'month');

        return collect(range(1, 12))->mapWithKeys(fn (int $month) => [
            $month => CarbonImmutable::create($year, $month, 1)->isAfter(today())
                ? null
                : ($total > 0 ? round(((int) ($counts[$month] ?? 0)) / $total * 100, 1) : 0.0),
        ])->all();
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return array<int, array{bmi: float|null, weight: float|null, count: int}>
     */
    private function monthlyAverages(Collection $ids, int $year): array
    {
        $rows = $ids->isEmpty() ? collect() : MonthlyWeightMonitoring::query()
            ->where('year', $year)->whereIn('personnel_id', $ids)
            ->selectRaw('month, avg(bmi) as avg_bmi, avg(weight_kg) as avg_weight, count(*) as aggregate')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        return collect(range(1, 12))->mapWithKeys(fn (int $month) => [$month => [
            'bmi' => isset($rows[$month]) && $rows[$month]->avg_bmi !== null ? round((float) $rows[$month]->avg_bmi, 1) : null,
            'weight' => isset($rows[$month]) ? round((float) $rows[$month]->avg_weight, 1) : null,
            'count' => (int) ($rows[$month]->aggregate ?? 0),
        ]])->all();
    }

    /**
     * @param  Collection<int, Personnel>  $rows
     * @param  Collection<int, BmiAssessment>  $latest
     * @return array<string, array{below: int, normal: int, above: int, none: int}>
     */
    private function unitDistribution(Collection $rows, Collection $latest): array
    {
        $units = Unit::withTrashed()->whereIn('id', $rows->pluck('unit_id')->filter()->unique())->pluck('code', 'id');

        return $rows->groupBy(fn (Personnel $row) => $units[$row->unit_id] ?? 'Unassigned')
            ->map(function (Collection $group) use ($latest) {
                $counts = ['below' => 0, 'normal' => 0, 'above' => 0, 'none' => 0];

                foreach ($group as $row) {
                    $counts[$latest->get($row->id)?->pnp_category?->value ?? 'none']++;
                }

                return $counts;
            })
            ->sortKeys()
            ->all();
    }
}
