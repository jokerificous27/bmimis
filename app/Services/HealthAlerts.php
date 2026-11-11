<?php

namespace App\Services;

use App\Enums\ClassificationCategory;
use App\Enums\InterventionStatus;
use App\Enums\MeasurementReview;
use App\Models\BmiAssessment;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use App\Models\PersonnelIntervention;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Health Service alerts and data quality checks, driven by configurable thresholds.
 */
class HealthAlerts
{
    public function __construct(
        private Settings $settings,
        private BmiStatistics $statistics,
        private ComplianceService $compliance,
        private MonthlyMonitoringService $monitoring,
    ) {}

    /**
     * @param  Builder<Personnel>  $personnel  authorized personnel query
     * @return array<string, Collection<int, mixed>>
     */
    public function all(User $user, Builder $personnel, int $year, int $month, int $limit = 8): array
    {
        $latest = $this->statistics->latestAssessments((clone $personnel)->active(), today());
        $deadlinePassed = today()->isAfter($this->monitoring->deadlineFor($year, $month));

        return [
            'missing_monthly' => $this->compliance->missingQuery($personnel, $year, $month)
                ->with(['rank:id,abbreviation', 'unit:id,code'])
                ->orderBy('last_name')
                ->limit($limit)
                ->get(),
            'missing_monthly_count' => collect([$this->compliance->missingQuery($personnel, $year, $month)->count()]),
            'deadline_passed' => collect([$deadlinePassed]),
            'abnormal' => $this->abnormal($latest, $limit),
            'follow_ups' => $this->followUpsDue($personnel, $limit),
            'outdated' => $this->outdatedAssessments($personnel, $latest, $limit),
            'weight_changes' => $this->significantWeightChanges($personnel)->take($limit),
            'measurement_reviews' => $this->measurementReviews($personnel, $limit),
            'low_compliance_units' => $this->compliance->byUnit($user, $year, $month)->reject(fn ($row) => $row['meets_target'])->take($limit)->values(),
        ];
    }

    /**
     * @param  Collection<int, BmiAssessment>  $latest
     * @return Collection<int, BmiAssessment>
     */
    public function abnormal(Collection $latest, int $limit): Collection
    {
        $abnormal = $latest->filter(fn (BmiAssessment $assessment) => $assessment->pnp_category !== null && $assessment->pnp_category !== ClassificationCategory::Normal)
            ->sortByDesc(fn (BmiAssessment $assessment) => abs($assessment->bmi - 22))
            ->take($limit);

        return BmiAssessment::query()->whereIn('id', $abnormal->pluck('id'))
            ->with(['personnel.rank:id,abbreviation', 'personnel.unit:id,code'])
            ->get()
            ->sortByDesc(fn (BmiAssessment $assessment) => abs($assessment->bmi - 22))
            ->values();
    }

    /**
     * @param  Builder<Personnel>  $personnel
     * @return Collection<int, PersonnelIntervention>
     */
    public function followUpsDue(Builder $personnel, int $limit, int $withinDays = 7): Collection
    {
        return PersonnelIntervention::query()
            ->where('status', InterventionStatus::Ongoing->value)
            ->whereIn('personnel_id', (clone $personnel)->select('personnel.id'))
            ->whereNotNull('next_follow_up_on')
            ->whereDate('next_follow_up_on', '<=', today()->addDays($withinDays))
            ->with(['personnel.rank:id,abbreviation', 'package:id,code,name'])
            ->orderBy('next_follow_up_on')
            ->limit($limit)
            ->get();
    }

    /**
     * Active personnel whose latest official assessment is older than the validity period, or who have none.
     *
     * @param  Builder<Personnel>  $personnel
     * @param  Collection<int, BmiAssessment>  $latest
     * @return Collection<int, Personnel>
     */
    public function outdatedAssessments(Builder $personnel, Collection $latest, ?int $limit = null): Collection
    {
        $cutoff = today()->subMonths($this->settings->int('assessment_validity_months'));

        $outdatedIds = (clone $personnel)->active()->pluck('id')
            ->filter(fn (int $id) => ! $latest->has($id) || $latest->get($id)->assessed_on->isBefore($cutoff));

        return Personnel::query()->whereIn('id', $outdatedIds)
            ->with(['rank:id,abbreviation', 'unit:id,code'])
            ->orderBy('last_name')
            ->when($limit, fn (Builder $query) => $query->limit($limit))
            ->get()
            ->each(fn (Personnel $person) => $person->setRelation('latestAssessment', $latest->get($person->id)));
    }

    /**
     * Personnel whose latest monthly weight changed by at least the configured threshold, or whose weight
     * moved in the same direction for the configured number of consecutive months.
     *
     * @param  Builder<Personnel>  $personnel
     * @return Collection<int, array{personnel: Personnel, latest: MonthlyWeightMonitoring, change: float, trend: string|null, significant: bool}>
     */
    public function significantWeightChanges(Builder $personnel): Collection
    {
        $threshold = $this->settings->float('significant_weight_change_kg');
        $streak = max(2, $this->settings->int('trend_consecutive_months'));
        $since = CarbonImmutable::today()->startOfMonth()->subMonths($streak + 1);

        $records = MonthlyWeightMonitoring::query()
            ->whereIn('personnel_id', (clone $personnel)->active()->select('personnel.id'))
            ->whereRaw('(year * 100 + month) >= ?', [$since->year * 100 + $since->month])
            ->orderBy('year')->orderBy('month')
            ->get(['id', 'personnel_id', 'year', 'month', 'weight_kg', 'bmi'])
            ->groupBy('personnel_id');

        $flagged = $records->map(function (Collection $history) use ($threshold, $streak) {
            if ($history->count() < 2) {
                return null;
            }

            $weights = $history->pluck('weight_kg')->values();
            $last = $history->last();
            $change = round($weights[$weights->count() - 1] - $weights[$weights->count() - 2], 2);

            $differences = $weights->sliding(2)->map(fn ($pair) => $pair->last() - $pair->first())->values();
            $recent = $differences->take(-$streak);
            $trend = match (true) {
                $recent->count() >= $streak && $recent->every(fn ($d) => $d > 0) => 'increasing',
                $recent->count() >= $streak && $recent->every(fn ($d) => $d < 0) => 'decreasing',
                default => null,
            };

            if (abs($change) < $threshold && $trend === null) {
                return null;
            }

            return ['personnel_id' => $last->personnel_id, 'latest' => $last, 'change' => $change, 'trend' => $trend, 'significant' => abs($change) >= $threshold];
        })->filter()->sortByDesc(fn ($row) => abs($row['change']));

        $people = Personnel::query()->whereIn('id', $flagged->pluck('personnel_id'))->with(['rank:id,abbreviation', 'unit:id,code'])->get()->keyBy('id');

        return $flagged->map(fn ($row) => $row + ['personnel' => $people->get($row['personnel_id'])])
            ->filter(fn ($row) => $row['personnel'] !== null)
            ->values();
    }

    /**
     * Weigh-ins whose BMI changed a lot since the last assessment, waiting for an administrator to
     * decide whether waist, hip and wrist are measured again.
     *
     * @param  Builder<Personnel>  $personnel
     * @return Collection<int, MonthlyWeightMonitoring>
     */
    public function measurementReviews(Builder $personnel, int $limit): Collection
    {
        return MonthlyWeightMonitoring::query()
            ->where('measurement_review', MeasurementReview::Pending->value)
            ->whereIn('personnel_id', (clone $personnel)->active()->select('personnel.id'))
            ->with(['personnel.rank:id,abbreviation'])
            ->orderByDesc('year')->orderByDesc('month')->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Data quality findings across the authorized population.
     *
     * @param  Builder<Personnel>  $personnel
     * @return array<string, Collection<int, mixed>>
     */
    public function dataQuality(Builder $personnel, int $year): array
    {
        $ids = (clone $personnel)->select('personnel.id');

        $incomplete = (clone $personnel)->active()->with(['rank:id,abbreviation', 'unit:id,code'])->orderBy('last_name')->get()
            ->map(fn (Personnel $person) => ['personnel' => $person, 'missing' => $person->missingProfileFields()])
            ->filter(fn ($row) => $row['missing'] !== [])
            ->values();

        $missingMeasurements = BmiAssessment::query()->official()->whereIn('personnel_id', $ids)
            ->where(fn (Builder $query) => $query->whereNull('waist_cm')->orWhereNull('hip_cm')->orWhereNull('wrist_cm'))
            ->with(['personnel.rank:id,abbreviation'])
            ->latest('assessed_on')
            ->limit(50)
            ->get();

        $duplicates = (clone $personnel)->get(['id', 'employee_number', 'last_name', 'first_name', 'date_of_birth', 'rank_id', 'unit_id'])
            ->groupBy(fn (Personnel $person) => mb_strtolower(trim($person->last_name)).'|'.mb_strtolower(trim($person->first_name)).'|'.$person->date_of_birth?->format('Y-m-d'))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->values();

        $unusualBmi = BmiAssessment::query()->official()->whereIn('personnel_id', $ids)
            ->where(fn (Builder $query) => $query->where('bmi', '<', 15)->orWhere('bmi', '>', 45))
            ->with('personnel.rank:id,abbreviation')
            ->limit(50)
            ->get();

        $unusualChanges = $this->unusualMonthlyChanges($ids, $year);

        $noHeight = MonthlyWeightMonitoring::query()->whereIn('personnel_id', $ids)->where('year', $year)->whereNull('height_cm')
            ->with('personnel.rank:id,abbreviation')
            ->limit(50)
            ->get();

        return [
            'incomplete_profiles' => $incomplete,
            'missing_measurements' => $missingMeasurements,
            'possible_duplicates' => $duplicates,
            'unusual_bmi' => $unusualBmi,
            'unusual_changes' => $unusualChanges,
            'monthly_without_height' => $noHeight,
        ];
    }

    /**
     * Month-to-month changes larger than twice the significant-change threshold, which often indicate
     * encoding errors (e.g. pounds encoded as kilograms).
     *
     * @param  Builder<Personnel>|\Illuminate\Database\Query\Builder  $ids
     * @return Collection<int, array{record: MonthlyWeightMonitoring, previous: MonthlyWeightMonitoring, change: float}>
     */
    private function unusualMonthlyChanges($ids, int $year): Collection
    {
        $limit = $this->settings->float('significant_weight_change_kg') * 2;

        return MonthlyWeightMonitoring::query()->whereIn('personnel_id', $ids)->where('year', $year)
            ->with('personnel.rank:id,abbreviation')
            ->orderBy('month')
            ->get()
            ->groupBy('personnel_id')
            ->flatMap(fn (Collection $history) => $history->sliding(2)
                ->map(fn ($pair) => ['previous' => $pair->first(), 'record' => $pair->last(), 'change' => round($pair->last()->weight_kg - $pair->first()->weight_kg, 2)])
                ->filter(fn ($row) => abs($row['change']) >= $limit))
            ->values();
    }
}
