<?php

namespace App\Services;

use App\Enums\MeasurementReview;
use App\Enums\Role;
use App\Models\BmiAssessment;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use App\Models\User;
use App\Notifications\MeasurementReviewRequested;
use App\Notifications\MonthlyWeightRecorded;
use App\Services\Bmi\BmiEvaluator;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly weight monitoring (one weigh-in per personnel per calendar month).
 * Monthly entries are kept separate from full BMI assessments; BMI is derived using the height
 * from the applicable assessment, and that height is stored with the entry so results stay reproducible.
 */
class MonthlyMonitoringService
{
    public function __construct(
        private BmiEvaluator $evaluator,
        private Settings $settings,
    ) {}

    public function exists(Personnel $personnel, int $year, int $month): bool
    {
        return $personnel->monthlyWeights()->where('year', $year)->where('month', $month)->exists();
    }

    /**
     * The assessment whose height applies to a month: the latest official assessment on or before
     * the end of the month, otherwise the earliest official assessment after it.
     */
    public function applicableAssessment(Personnel $personnel, int $year, int $month): ?BmiAssessment
    {
        $endOfMonth = CarbonImmutable::create($year, $month, 1)->endOfMonth();

        return $personnel->assessments()->official()
            ->whereDate('assessed_on', '<=', $endOfMonth)
            ->orderByDesc('assessed_on')->orderByDesc('id')
            ->first()
            ?? $personnel->assessments()->official()->orderBy('assessed_on')->first();
    }

    public function record(
        Personnel $personnel,
        int $year,
        int $month,
        float $weightKg,
        ?CarbonInterface $measuredOn = null,
        ?string $remarks = null,
        ?float $heightCm = null,
        bool $notify = true,
        string $captureMethod = 'manual',
        ?string $photoPath = null,
        ?float $aiWeightKg = null,
        ?string $aiConfidence = null,
    ): MonthlyWeightMonitoring {
        $this->assertValidPeriod($year, $month);

        return DB::transaction(function () use ($personnel, $year, $month, $weightKg, $measuredOn, $remarks, $heightCm, $notify, $captureMethod, $photoPath, $aiWeightKg, $aiConfidence): MonthlyWeightMonitoring {
            $duplicate = MonthlyWeightMonitoring::query()
                ->where('personnel_id', $personnel->id)->where('year', $year)->where('month', $month)
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'weight_kg' => __('A weight for :month :year is already recorded. Use "Correct entry" to change it.', [
                        'month' => MonthlyWeightMonitoring::MONTHS[$month],
                        'year' => $year,
                    ]),
                ]);
            }

            $record = new MonthlyWeightMonitoring([
                'personnel_id' => $personnel->id,
                'unit_id' => $personnel->unit_id,
                'year' => $year,
                'month' => $month,
                'measured_on' => $measuredOn,
                'remarks' => $remarks,
                'recorded_by' => Auth::id(),
                'capture_method' => $captureMethod,
                'photo_path' => $photoPath,
                'ai_weight_kg' => $aiWeightKg,
                'ai_confidence' => $aiConfidence,
            ]);

            $this->applyMeasurement($record, $personnel, $weightKg, $heightCm);
            $this->assessMeasurementReview($record, $personnel);
            $record->save();

            $this->requestMeasurementReview($record, $notify);

            if ($notify) {
                $personnel->user?->notify(new MonthlyWeightRecorded($record));
            }

            return $record;
        });
    }

    /**
     * Correct a previously recorded weight. The reason is written to the audit trail.
     */
    public function correct(MonthlyWeightMonitoring $record, float $weightKg, string $reason, ?CarbonInterface $measuredOn = null, ?string $remarks = null, ?float $heightCm = null): MonthlyWeightMonitoring
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['correction_reason' => __('Please state the reason for the correction.')]);
        }

        $record->fill(['measured_on' => $measuredOn ?? $record->measured_on, 'remarks' => $remarks ?? $record->remarks]);
        $wasPending = $record->needsMeasurementReview();
        $this->applyMeasurement($record, $record->personnel, $weightKg, $heightCm ?? $record->height_cm);
        $this->assessMeasurementReview($record, $record->personnel);
        $record->auditRemarks = 'Correction: '.$reason;
        $record->save();

        if (! $wasPending) {
            $this->requestMeasurementReview($record);
        }

        return $record;
    }

    /**
     * Decide whether administrators should be asked to measure waist, hip and wrist again: the BMI of
     * this weigh-in differs from the last assessment (where those were measured) by at least the
     * configured number of BMI points. A review that was already answered is never reopened.
     */
    private function assessMeasurementReview(MonthlyWeightMonitoring $record, Personnel $personnel): void
    {
        if (in_array($record->measurement_review, [MeasurementReview::Updated, MeasurementReview::Declined], true)) {
            return;
        }

        $baseline = $record->bmi === null ? null : $this->measurementBaseline($record, $personnel);
        $change = $baseline ? round($record->bmi - $baseline->bmi, 2) : null;
        $significant = $change !== null && abs($change) >= $this->settings->float('significant_bmi_change');

        $record->forceFill([
            'measurement_review' => $significant ? MeasurementReview::Pending : null,
            'bmi_change' => $significant ? $change : null,
            'baseline_assessment_id' => $significant ? $baseline->id : null,
        ]);
    }

    /**
     * The assessment a weigh-in is compared with: the latest official one taken before the weigh-in.
     * Null when the weigh-in is not the personnel's newest measurement (back-filled months), or when
     * an assessment was made on or after that day (the measurements are then already current).
     */
    private function measurementBaseline(MonthlyWeightMonitoring $record, Personnel $personnel): ?BmiAssessment
    {
        $date = $record->measurementDate();
        $period = $record->year * 100 + $record->month;

        $newerWeighIn = $personnel->monthlyWeights()->whereRaw('(year * 100 + month) > ?', [$period])->exists();
        $currentAssessment = $personnel->assessments()->official()->whereDate('assessed_on', '>=', $date)->exists();

        if ($newerWeighIn || $currentAssessment) {
            return null;
        }

        return $personnel->assessments()->official()
            ->whereDate('assessed_on', '<', $date)
            ->orderByDesc('assessed_on')->orderByDesc('id')
            ->first();
    }

    /**
     * Withdraw older open requests for the same personnel, so there is only ever one question per
     * person, and (unless notifications are off, e.g. imports) notify the administrators.
     */
    private function requestMeasurementReview(MonthlyWeightMonitoring $record, bool $notify = true): void
    {
        if (! $record->needsMeasurementReview()) {
            return;
        }

        MonthlyWeightMonitoring::query()
            ->where('personnel_id', $record->personnel_id)
            ->whereKeyNot($record->id)
            ->where('measurement_review', MeasurementReview::Pending->value)
            ->get()
            ->each(function (MonthlyWeightMonitoring $older) use ($record): void {
                $older->forceFill(['measurement_review' => null]);
                $older->auditRemarks = "Measurement review moved to the {$record->monthName()} {$record->year} weigh-in";
                $older->save();
            });

        if (! $notify) {
            return;
        }

        User::query()->where('is_active', true)->where('role', '!=', Role::Personnel->value)->get()
            ->filter(fn (User $user) => $user->can('reviewMeasurements', $record))
            ->each(fn (User $user) => $user->notify(new MeasurementReviewRequested($record)));
    }

    /**
     * A newly submitted assessment answers open measurement reviews of weigh-ins up to its date:
     * waist, hip and wrist were just measured (or deliberately left as they are).
     */
    public function resolveMeasurementReviews(BmiAssessment $assessment): void
    {
        $date = CarbonImmutable::parse($assessment->assessed_on);

        MonthlyWeightMonitoring::query()
            ->where('personnel_id', $assessment->personnel_id)
            ->where('measurement_review', MeasurementReview::Pending->value)
            ->get()
            ->filter(fn (MonthlyWeightMonitoring $record) => $record->measurementDate()->startOfDay()->lessThanOrEqualTo($date->startOfDay()))
            ->each(function (MonthlyWeightMonitoring $record) use ($assessment): void {
                $record->forceFill([
                    'measurement_review' => MeasurementReview::Updated,
                    'measurement_reviewed_by' => Auth::id(),
                    'measurement_reviewed_at' => now(),
                ]);
                $record->auditRemarks = "Measurements updated by assessment #{$assessment->id}";
                $record->save();
            });
    }

    /**
     * An administrator decided that waist, hip and wrist do not need to be measured again.
     */
    public function declineMeasurementReview(MonthlyWeightMonitoring $record): void
    {
        $record->forceFill([
            'measurement_review' => MeasurementReview::Declined,
            'measurement_reviewed_by' => Auth::id(),
            'measurement_reviewed_at' => now(),
        ]);
        $record->auditRemarks = 'Measurement review: no update needed';
        $record->save();
    }

    /**
     * January–December sheet for one personnel and year, including month-to-month differences.
     *
     * @return Collection<int, array{month: int, name: string, record: MonthlyWeightMonitoring|null, difference: float|null, trend: string|null, status: string}>
     */
    public function yearSheet(Personnel $personnel, int $year): Collection
    {
        $records = $personnel->monthlyWeights()
            ->where(fn ($query) => $query->where('year', $year)->orWhere(fn ($query) => $query->where('year', $year - 1)->where('month', 12)))
            ->with('recorder:id,name')
            ->get()
            ->keyBy(fn (MonthlyWeightMonitoring $record) => $record->year === $year ? $record->month : 0);

        $previousWeight = $records->get(0)?->weight_kg;

        return collect(MonthlyWeightMonitoring::MONTHS)->map(function (string $name, int $month) use ($records, $year, &$previousWeight) {
            $record = $records->get($month);
            $difference = null;
            $trend = null;

            if ($record) {
                if ($previousWeight !== null) {
                    $difference = round($record->weight_kg - $previousWeight, 2);
                    $trend = $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'same');
                }

                $previousWeight = $record->weight_kg;
            }

            return [
                'month' => $month,
                'name' => $name,
                'record' => $record,
                'difference' => $difference,
                'trend' => $trend,
                'status' => $record ? 'recorded' : $this->periodStatus($year, $month),
            ];
        })->values();
    }

    /**
     * Status of a month without a record: "upcoming", "pending" (before the deadline) or "missing".
     */
    public function periodStatus(int $year, int $month): string
    {
        $start = CarbonImmutable::create($year, $month, 1);

        if ($start->isAfter(today()->endOfMonth())) {
            return 'upcoming';
        }

        return today()->isAfter($this->deadlineFor($year, $month)) ? 'missing' : 'pending';
    }

    public function deadlineFor(int $year, int $month): CarbonImmutable
    {
        $start = CarbonImmutable::create($year, $month, 1);
        $day = min(max(1, $this->settings->int('monitoring_deadline_day')), $start->daysInMonth);

        return $start->setDay($day)->endOfDay();
    }

    private function applyMeasurement(MonthlyWeightMonitoring $record, Personnel $personnel, float $weightKg, ?float $heightCm): void
    {
        $source = $heightCm ? null : $this->applicableAssessment($personnel, $record->year, $record->month);
        $height = $heightCm ?? $source?->height_cm;

        $record->weight_kg = $weightKg;
        $record->height_cm = $height;
        $record->height_source_assessment_id = $source?->id ?? ($heightCm ? $record->height_source_assessment_id : null);

        if (! $height) {
            $record->forceFill(['bmi' => null, 'pnp_classification' => null, 'pnp_category' => null, 'who_classification' => null, 'who_category' => null]);

            return;
        }

        $date = $record->measured_on ? CarbonImmutable::parse($record->measured_on) : CarbonImmutable::create($record->year, $record->month, 1)->endOfMonth();
        $evaluation = $this->evaluator->evaluate($height, $weightKg, $date, $personnel->sex, $personnel->ageOn($date));

        $record->forceFill([
            'bmi' => $evaluation->bmi,
            'pnp_classification' => $evaluation->pnpClassification?->label,
            'pnp_category' => $evaluation->pnpClassification?->category,
            'who_classification' => $evaluation->whoClassification?->label,
            'who_category' => $evaluation->whoClassification?->category,
        ]);
    }

    private function assertValidPeriod(int $year, int $month): void
    {
        if ($month < 1 || $month > 12 || $year < 2000) {
            throw ValidationException::withMessages(['month' => __('Invalid monitoring period.')]);
        }

        if (CarbonImmutable::create($year, $month, 1)->isAfter(today())) {
            throw ValidationException::withMessages(['month' => __('Weights cannot be recorded for a future month.')]);
        }
    }
}
