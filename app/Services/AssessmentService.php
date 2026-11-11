<?php

namespace App\Services;

use App\Enums\AssessmentStatus;
use App\Models\ActivityLog;
use App\Models\BmiAssessment;
use App\Models\Personnel;
use App\Models\User;
use App\Notifications\AssessmentAvailable;
use App\Services\Bmi\BmiEvaluation;
use App\Services\Bmi\BmiEvaluator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates, corrects, submits and certifies full BMI assessments.
 * Historical assessments are never overwritten silently: corrections require a reason and are audited,
 * and a new same-day assessment supersedes (rather than replaces) the previous one.
 */
class AssessmentService
{
    /**
     * Assessment attributes supplied by the encoder (everything else is computed).
     */
    private const INPUT_FIELDS = [
        'assessed_on', 'height_cm', 'weight_kg', 'waist_cm', 'hip_cm', 'wrist_cm', 'intervention_package_id',
        'unit_representative_name', 'unit_representative_designation',
        'health_service_representative_name', 'health_service_representative_designation', 'remarks',
    ];

    public function __construct(
        private BmiEvaluator $evaluator,
        private MonthlyMonitoringService $monitoring,
    ) {}

    /**
     * @param  array{min: float|null, max: float|null}|null  $manualIbw
     */
    public function evaluate(Personnel $personnel, float $heightCm, float $weightKg, CarbonInterface $date, ?float $waistCm = null, ?float $hipCm = null, ?array $manualIbw = null): BmiEvaluation
    {
        return $this->evaluator->evaluate(
            heightCm: $heightCm,
            weightKg: $weightKg,
            date: $date,
            sex: $personnel->sex,
            age: $personnel->ageOn($date),
            waistCm: $waistCm,
            hipCm: $hipCm,
            manualIbw: $manualIbw,
        );
    }

    /**
     * An existing, non-superseded assessment for the same personnel and date.
     */
    public function findSameDayAssessment(Personnel $personnel, CarbonInterface $date, ?int $exceptId = null): ?BmiAssessment
    {
        return $personnel->assessments()
            ->whereDate('assessed_on', $date)
            ->where('status', '!=', AssessmentStatus::Superseded->value)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Personnel $personnel, array $data, bool $submit, bool $recordMonthlyWeight = false, bool $confirmReplace = false): BmiAssessment
    {
        $date = CarbonImmutable::parse($data['assessed_on']);
        $existing = $this->findSameDayAssessment($personnel, $date);

        if ($existing && ! $confirmReplace) {
            throw ValidationException::withMessages([
                'assessed_on' => __('An assessment dated :date already exists for this personnel. Confirm to supersede it.', ['date' => $date->format('F j, Y')]),
            ]);
        }

        return DB::transaction(function () use ($personnel, $data, $submit, $recordMonthlyWeight, $date, $existing): BmiAssessment {
            $assessment = new BmiAssessment(Arr::only($data, self::INPUT_FIELDS));
            $assessment->forceFill([
                'personnel_id' => $personnel->id,
                'unit_id' => $personnel->unit_id,
                'rank_id' => $personnel->rank_id,
                'sex' => $personnel->sex,
                'age_at_assessment' => $personnel->ageOn($date),
                'status' => AssessmentStatus::Draft,
                'encoded_by' => Auth::id(),
            ]);

            $this->applyEvaluation($assessment, $personnel, $data);

            if ($submit) {
                $assessment->forceFill(['status' => AssessmentStatus::Submitted, 'submitted_at' => now()]);
            }

            $assessment->save();

            if ($existing) {
                $this->supersede($existing, $assessment);
            }

            if ($submit) {
                $this->afterSubmission($assessment, $recordMonthlyWeight);
            }

            return $assessment;
        });
    }

    /**
     * Update a draft, or correct a submitted/certified assessment (a reason is mandatory for corrections).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(BmiAssessment $assessment, array $data, ?string $correctionReason = null, bool $submit = false, bool $recordMonthlyWeight = false): BmiAssessment
    {
        if ($assessment->status === AssessmentStatus::Superseded) {
            throw ValidationException::withMessages(['assessed_on' => __('Superseded assessments are kept for history and cannot be edited.')]);
        }

        if ($assessment->isOfficial() && blank($correctionReason)) {
            throw ValidationException::withMessages(['correction_reason' => __('Please state the reason for correcting this assessment.')]);
        }

        return DB::transaction(function () use ($assessment, $data, $correctionReason, $submit, $recordMonthlyWeight): BmiAssessment {
            $personnel = $assessment->personnel;
            $wasDraft = $assessment->status === AssessmentStatus::Draft;

            $assessment->fill(Arr::only($data, self::INPUT_FIELDS));
            $assessment->age_at_assessment = $personnel->ageOn(CarbonImmutable::parse($assessment->assessed_on));
            $this->applyEvaluation($assessment, $personnel, $data);

            if ($assessment->status === AssessmentStatus::Certified) {
                // A corrected result must be certified again.
                $assessment->forceFill(['status' => AssessmentStatus::Submitted, 'certified_by' => null, 'certified_at' => null]);
            }

            if ($wasDraft && $submit) {
                $assessment->forceFill(['status' => AssessmentStatus::Submitted, 'submitted_at' => now()]);
            }

            $assessment->auditRemarks = $correctionReason ? 'Correction: '.$correctionReason : null;
            $assessment->save();

            if ($wasDraft && $submit) {
                $this->afterSubmission($assessment, $recordMonthlyWeight);
            }

            return $assessment;
        });
    }

    public function certify(BmiAssessment $assessment, User $certifier, ?string $remarks = null): void
    {
        if ($assessment->status !== AssessmentStatus::Submitted) {
            throw ValidationException::withMessages(['certification' => __('Only submitted assessments can be certified.')]);
        }

        DB::transaction(function () use ($assessment, $certifier, $remarks): void {
            $assessment->forceFill([
                'status' => AssessmentStatus::Certified,
                'certified_by' => $certifier->id,
                'certified_at' => now(),
                'certification_remarks' => $remarks,
            ]);
            $assessment->auditRemarks = 'Certified correct'.($remarks ? ': '.$remarks : '');
            $assessment->save();

            ActivityLog::record('assessment.certified', $assessment, $assessment->personnel_id, remarks: $remarks, description: 'Assessment certified correct');
        });

        $assessment->personnel->user?->notify(new AssessmentAvailable($assessment, certified: true));
    }

    public function returnToDraft(BmiAssessment $assessment, string $reason): void
    {
        if ($assessment->status !== AssessmentStatus::Submitted) {
            throw ValidationException::withMessages(['certification' => __('Only submitted assessments can be returned.')]);
        }

        $assessment->forceFill(['status' => AssessmentStatus::Draft, 'submitted_at' => null]);
        $assessment->auditRemarks = 'Returned to encoder: '.$reason;
        $assessment->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyEvaluation(BmiAssessment $assessment, Personnel $personnel, array $data): void
    {
        $evaluation = $this->evaluate(
            personnel: $personnel,
            heightCm: (float) $assessment->height_cm,
            weightKg: (float) $assessment->weight_kg,
            date: CarbonImmutable::parse($assessment->assessed_on),
            waistCm: $assessment->waist_cm ? (float) $assessment->waist_cm : null,
            hipCm: $assessment->hip_cm ? (float) $assessment->hip_cm : null,
            manualIbw: ['min' => isset($data['ibw_min_kg']) ? (float) $data['ibw_min_kg'] : null, 'max' => isset($data['ibw_max_kg']) ? (float) $data['ibw_max_kg'] : null],
        );

        $assessment->forceFill($evaluation->toSnapshot());

        if (! array_key_exists('intervention_package_id', $data) || blank($data['intervention_package_id'])) {
            $assessment->intervention_package_id = $evaluation->suggestedInterventionPackageId();
        }
    }

    private function supersede(BmiAssessment $previous, BmiAssessment $replacement): void
    {
        $previous->forceFill([
            'status' => AssessmentStatus::Superseded,
            'superseded_by_id' => $replacement->id,
        ]);
        $previous->auditRemarks = "Superseded by assessment #{$replacement->id}";
        $previous->save();
    }

    private function afterSubmission(BmiAssessment $assessment, bool $recordMonthlyWeight): void
    {
        $date = CarbonImmutable::parse($assessment->assessed_on);

        if ($recordMonthlyWeight && ! $this->monitoring->exists($assessment->personnel, $date->year, $date->month)) {
            $this->monitoring->record(
                personnel: $assessment->personnel,
                year: $date->year,
                month: $date->month,
                weightKg: (float) $assessment->weight_kg,
                measuredOn: $date,
                remarks: __('Recorded from BMI assessment #:id', ['id' => $assessment->id]),
                heightCm: (float) $assessment->height_cm,
                notify: false,
            );
        }

        $this->monitoring->resolveMeasurementReviews($assessment);

        $assessment->personnel->user?->notify(new AssessmentAvailable($assessment));
    }
}
