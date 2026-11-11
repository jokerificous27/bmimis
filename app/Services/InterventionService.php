<?php

namespace App\Services;

use App\Enums\InterventionStatus;
use App\Models\BmiAssessment;
use App\Models\InterventionFollowUp;
use App\Models\InterventionPackage;
use App\Models\Personnel;
use App\Models\PersonnelIntervention;
use App\Notifications\FollowUpScheduled;
use App\Notifications\InterventionAssigned;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assignment and follow-up of Health Service intervention packages.
 */
class InterventionService
{
    public function __construct(private Settings $settings) {}

    public function assign(
        Personnel $personnel,
        InterventionPackage $package,
        CarbonInterface $assignedOn,
        ?CarbonInterface $nextFollowUpOn = null,
        ?BmiAssessment $assessment = null,
        ?float $targetWeightKg = null,
        ?string $remarks = null,
    ): PersonnelIntervention {
        if ($personnel->activeIntervention()->exists()) {
            throw ValidationException::withMessages(['intervention_package_id' => __('This personnel already has an ongoing intervention. Complete or discontinue it first.')]);
        }

        $intervention = DB::transaction(function () use ($personnel, $package, $assignedOn, $nextFollowUpOn, $assessment, $targetWeightKg, $remarks) {
            $assessment ??= $personnel->latestAssessment;
            $latestWeight = $personnel->monthlyWeights()->orderByDesc('year')->orderByDesc('month')->value('weight_kg') ?? $assessment?->weight_kg;
            $interval = $package->follow_up_interval_days ?: $this->settings->int('follow_up_default_days');

            return PersonnelIntervention::create([
                'personnel_id' => $personnel->id,
                'intervention_package_id' => $package->id,
                'bmi_assessment_id' => $assessment?->id,
                'assigned_on' => $assignedOn,
                'starting_weight_kg' => $latestWeight,
                'target_weight_kg' => $targetWeightKg ?? ($assessment?->weight_difference_kg ? $this->targetFromAssessment($assessment) : null),
                'next_follow_up_on' => $nextFollowUpOn ?? CarbonImmutable::parse($assignedOn)->addDays($interval),
                'status' => InterventionStatus::Ongoing,
                'remarks' => $remarks,
                'assigned_by' => Auth::id(),
            ]);
        });

        $personnel->user?->notify(new InterventionAssigned($intervention->load('package')));

        return $intervention;
    }

    public function recordFollowUp(PersonnelIntervention $intervention, CarbonInterface $date, ?float $weightKg, ?string $notes, ?CarbonInterface $nextFollowUpOn): InterventionFollowUp
    {
        $this->assertOngoing($intervention);

        $followUp = DB::transaction(function () use ($intervention, $date, $weightKg, $notes, $nextFollowUpOn) {
            $followUp = $intervention->followUps()->create([
                'follow_up_on' => $date,
                'weight_kg' => $weightKg,
                'notes' => $notes,
                'recorded_by' => Auth::id(),
            ]);

            $intervention->update(['next_follow_up_on' => $nextFollowUpOn]);

            return $followUp;
        });

        if ($nextFollowUpOn) {
            $intervention->personnel->user?->notify(new FollowUpScheduled($intervention->load('package')));
        }

        return $followUp;
    }

    public function close(PersonnelIntervention $intervention, InterventionStatus $status, CarbonInterface $closedOn, string $outcome): void
    {
        $this->assertOngoing($intervention);

        if ($status === InterventionStatus::Ongoing) {
            throw ValidationException::withMessages(['status' => __('Choose Completed or Discontinued.')]);
        }

        $intervention->update([
            'status' => $status,
            'completed_on' => $closedOn,
            'outcome' => $outcome,
            'next_follow_up_on' => null,
        ]);
    }

    private function targetFromAssessment(BmiAssessment $assessment): ?float
    {
        return match ($assessment->weight_action?->value) {
            'lose' => $assessment->ibw_max_kg,
            'gain' => $assessment->ibw_min_kg,
            default => null,
        };
    }

    private function assertOngoing(PersonnelIntervention $intervention): void
    {
        if ($intervention->status !== InterventionStatus::Ongoing) {
            throw ValidationException::withMessages(['status' => __('This intervention is already closed.')]);
        }
    }
}
