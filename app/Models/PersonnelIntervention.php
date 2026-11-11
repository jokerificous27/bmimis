<?php

namespace App\Models;

use App\Enums\InterventionStatus;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksAuthors;
use Carbon\CarbonInterface;
use Database\Factories\PersonnelInterventionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $personnel_id
 * @property int $intervention_package_id
 * @property int|null $bmi_assessment_id
 * @property CarbonInterface $assigned_on
 * @property float|null $starting_weight_kg
 * @property float|null $target_weight_kg
 * @property CarbonInterface|null $next_follow_up_on
 * @property InterventionStatus $status
 * @property CarbonInterface|null $completed_on
 * @property string|null $outcome
 * @property string|null $remarks
 * @property string|null $health_service_notes
 * @property int|null $assigned_by
 * @property-read Personnel $personnel
 * @property-read InterventionPackage $package
 * @property-read BmiAssessment|null $assessment
 */
#[Fillable([
    'personnel_id', 'intervention_package_id', 'bmi_assessment_id', 'assigned_on', 'starting_weight_kg', 'target_weight_kg',
    'next_follow_up_on', 'status', 'completed_on', 'outcome', 'remarks', 'health_service_notes', 'assigned_by',
])]
class PersonnelIntervention extends Model
{
    /** @use HasFactory<PersonnelInterventionFactory> */
    use HasFactory, RecordsActivity, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_on' => 'date',
            'next_follow_up_on' => 'date',
            'completed_on' => 'date',
            'starting_weight_kg' => 'float',
            'target_weight_kg' => 'float',
            'status' => InterventionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Personnel, $this>
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class)->withTrashed();
    }

    /**
     * @return BelongsTo<InterventionPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(InterventionPackage::class, 'intervention_package_id')->withTrashed();
    }

    /**
     * @return BelongsTo<BmiAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(BmiAssessment::class, 'bmi_assessment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return HasMany<InterventionFollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(InterventionFollowUp::class)->orderByDesc('follow_up_on')->orderByDesc('id');
    }

    public function isFollowUpOverdue(): bool
    {
        return $this->status === InterventionStatus::Ongoing
            && $this->next_follow_up_on !== null
            && $this->next_follow_up_on->isBefore(today());
    }

    public function auditName(): string
    {
        return 'intervention';
    }
}
