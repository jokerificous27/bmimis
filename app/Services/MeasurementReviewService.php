<?php

namespace App\Services;

use App\Models\BmiAssessment;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Answers the question raised by a large BMI change at a monthly weigh-in: should waist, hip and
 * wrist be measured again? Updating never overwrites history: the new measurements are stored as a
 * new assessment dated on the weigh-in, using the weight of that weigh-in.
 */
class MeasurementReviewService
{
    public function __construct(
        private AssessmentService $assessments,
        private MonthlyMonitoringService $monitoring,
    ) {}

    /**
     * @param  array{waist_cm: float|null, hip_cm: float|null, wrist_cm: float|null}  $measurements
     */
    public function update(MonthlyWeightMonitoring $record, array $measurements): BmiAssessment
    {
        $this->assertPending($record);

        $baseline = $record->baselineAssessment;
        $height = $record->height_cm ?? $baseline?->height_cm;

        if (! $height) {
            throw ValidationException::withMessages(['waist_cm' => __('There is no height on record for this personnel. Encode a full BMI assessment instead.')]);
        }

        return DB::transaction(fn (): BmiAssessment => $this->assessments->create(
            personnel: $record->personnel,
            data: [
                'assessed_on' => $record->measurementDate()->format('Y-m-d'),
                'height_cm' => $height,
                'weight_kg' => $record->weight_kg,
                'waist_cm' => $measurements['waist_cm'],
                'hip_cm' => $measurements['hip_cm'],
                'wrist_cm' => $measurements['wrist_cm'],
                'unit_representative_name' => $baseline?->unit_representative_name,
                'unit_representative_designation' => $baseline?->unit_representative_designation,
                'health_service_representative_name' => $baseline?->health_service_representative_name,
                'health_service_representative_designation' => $baseline?->health_service_representative_designation,
                'remarks' => __('Waist, hip and wrist updated after a BMI change of :change at the :month :year weigh-in. Height carried over from the previous assessment.', [
                    'change' => ($record->bmi_change > 0 ? '+' : '').number_format((float) $record->bmi_change, 1),
                    'month' => $record->monthName(),
                    'year' => $record->year,
                ]),
            ],
            submit: true,
        ));
    }

    /**
     * Waist, hip and wrist measured at the Weigh-in Station together with the monthly weight, stored
     * as a new assessment dated on the weigh-in with the height of the latest assessment. Save it
     * before the weight, so the weigh-in is compared with current measurements and raises no review.
     *
     * @param  array{waist_cm: float|null, hip_cm: float|null, wrist_cm: float|null}  $measurements
     */
    public function recordAtWeighIn(Personnel $personnel, float $weightKg, CarbonInterface $date, array $measurements): BmiAssessment
    {
        $baseline = $personnel->latestAssessment;

        if (! $baseline?->height_cm) {
            throw ValidationException::withMessages(['waist_cm' => __('There is no height on record for this personnel. Encode a full BMI assessment instead.')]);
        }

        return $this->assessments->create(
            personnel: $personnel,
            data: [
                'assessed_on' => $date->format('Y-m-d'),
                'height_cm' => $baseline->height_cm,
                'weight_kg' => $weightKg,
                'waist_cm' => $measurements['waist_cm'],
                'hip_cm' => $measurements['hip_cm'],
                'wrist_cm' => $measurements['wrist_cm'],
                'unit_representative_name' => $baseline->unit_representative_name,
                'unit_representative_designation' => $baseline->unit_representative_designation,
                'health_service_representative_name' => $baseline->health_service_representative_name,
                'health_service_representative_designation' => $baseline->health_service_representative_designation,
                'remarks' => __('Waist, hip and wrist measured at the :month weigh-in (Weigh-in Station). Height carried over from the previous assessment.', ['month' => $date->format('F Y')]),
            ],
            submit: true,
        );
    }

    public function decline(MonthlyWeightMonitoring $record): void
    {
        $this->assertPending($record);

        $this->monitoring->declineMeasurementReview($record);
    }

    private function assertPending(MonthlyWeightMonitoring $record): void
    {
        if (! $record->needsMeasurementReview()) {
            throw ValidationException::withMessages(['waist_cm' => __('This measurement review was already answered.')]);
        }
    }
}
