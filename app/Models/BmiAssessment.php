<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use App\Enums\ClassificationCategory;
use App\Enums\IbwMethod;
use App\Enums\Sex;
use App\Enums\WeightAction;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksAuthors;
use Carbon\CarbonInterface;
use Database\Factories\BmiAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A complete BMI assessment, equivalent to one PNP Health Service BMI Form.
 * Classification results are stored as snapshots so historic results stay reproducible
 * even when standards are later changed.
 *
 * @property int $id
 * @property int $personnel_id
 * @property int|null $unit_id
 * @property int|null $rank_id
 * @property CarbonInterface $assessed_on
 * @property int|null $age_at_assessment
 * @property Sex|null $sex
 * @property float $height_cm
 * @property float $weight_kg
 * @property float|null $waist_cm
 * @property float|null $hip_cm
 * @property float|null $wrist_cm
 * @property float $bmi
 * @property float|null $waist_hip_ratio
 * @property IbwMethod $ibw_method
 * @property float|null $ibw_min_kg
 * @property float|null $ibw_max_kg
 * @property float|null $weight_difference_kg
 * @property WeightAction|null $weight_action
 * @property int|null $pnp_classification_id
 * @property string|null $pnp_classification
 * @property ClassificationCategory|null $pnp_category
 * @property int|null $who_classification_id
 * @property string|null $who_classification
 * @property ClassificationCategory|null $who_category
 * @property bool $requires_intervention
 * @property int|null $intervention_package_id
 * @property AssessmentStatus $status
 * @property string|null $unit_representative_name
 * @property string|null $unit_representative_designation
 * @property string|null $health_service_representative_name
 * @property string|null $health_service_representative_designation
 * @property int|null $encoded_by
 * @property CarbonInterface|null $submitted_at
 * @property int|null $certified_by
 * @property CarbonInterface|null $certified_at
 * @property string|null $certification_remarks
 * @property int|null $superseded_by_id
 * @property string|null $remarks
 * @property string|null $photo_front_path
 * @property string|null $photo_right_path
 * @property string|null $photo_left_path
 * @property-read Personnel $personnel
 * @property-read Unit|null $unit
 * @property-read Rank|null $rank
 * @property-read InterventionPackage|null $interventionPackage
 * @property-read User|null $encoder
 * @property-read User|null $certifier
 */
#[Fillable([
    'personnel_id', 'unit_id', 'rank_id', 'assessed_on', 'age_at_assessment', 'sex',
    'height_cm', 'weight_kg', 'waist_cm', 'hip_cm', 'wrist_cm',
    'bmi', 'waist_hip_ratio', 'ibw_method', 'ibw_min_kg', 'ibw_max_kg', 'weight_difference_kg', 'weight_action',
    'pnp_classification_id', 'pnp_classification', 'pnp_category',
    'who_classification_id', 'who_classification', 'who_category',
    'requires_intervention', 'intervention_package_id',
    'unit_representative_name', 'unit_representative_designation',
    'health_service_representative_name', 'health_service_representative_designation',
    'remarks', 'photo_front_path', 'photo_right_path', 'photo_left_path',
])]
class BmiAssessment extends Model
{
    /** @use HasFactory<BmiAssessmentFactory> */
    use HasFactory, RecordsActivity, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessed_on' => 'date',
            'sex' => Sex::class,
            'height_cm' => 'float',
            'weight_kg' => 'float',
            'waist_cm' => 'float',
            'hip_cm' => 'float',
            'wrist_cm' => 'float',
            'bmi' => 'float',
            'waist_hip_ratio' => 'float',
            'ibw_method' => IbwMethod::class,
            'ibw_min_kg' => 'float',
            'ibw_max_kg' => 'float',
            'weight_difference_kg' => 'float',
            'weight_action' => WeightAction::class,
            'pnp_category' => ClassificationCategory::class,
            'who_category' => ClassificationCategory::class,
            'requires_intervention' => 'boolean',
            'status' => AssessmentStatus::class,
            'submitted_at' => 'datetime',
            'certified_at' => 'datetime',
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
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Rank, $this>
     */
    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    /**
     * @return BelongsTo<InterventionPackage, $this>
     */
    public function interventionPackage(): BelongsTo
    {
        return $this->belongsTo(InterventionPackage::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function certifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfficial(Builder $query): void
    {
        $query->whereIn('status', AssessmentStatus::officialValues());
    }

    public function isOfficial(): bool
    {
        return in_array($this->status->value, AssessmentStatus::officialValues(), true);
    }

    /**
     * "Weight to lose / maintain" value exactly as printed on the form.
     */
    public function weightActionText(): string
    {
        return $this->weight_action?->describe($this->weight_difference_kg) ?? '—';
    }

    public function ibwRangeText(): string
    {
        if ($this->ibw_min_kg === null || $this->ibw_max_kg === null) {
            return '—';
        }

        return number_format($this->ibw_min_kg, 1).' kg – '.number_format($this->ibw_max_kg, 1).' kg';
    }

    /**
     * @return array<string, string|null>
     */
    public function photoPaths(): array
    {
        return [
            'right' => $this->photo_right_path,
            'front' => $this->photo_front_path,
            'left' => $this->photo_left_path,
        ];
    }

    public function auditName(): string
    {
        return 'assessment';
    }
}
