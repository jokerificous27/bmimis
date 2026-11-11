<?php

namespace App\Models;

use App\Enums\ClassificationCategory;
use App\Enums\Sex;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\BmiClassificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One BMI band within a standard. Lower bound is inclusive, upper bound is exclusive.
 * Optional age and sex limits allow age- or sex-specific official thresholds.
 *
 * @property int $id
 * @property int $bmi_standard_id
 * @property string $label
 * @property ClassificationCategory $category
 * @property float|null $min_bmi
 * @property float|null $max_bmi
 * @property int|null $min_age
 * @property int|null $max_age
 * @property Sex|null $sex
 * @property bool $requires_intervention
 * @property int|null $intervention_package_id
 * @property string $color
 * @property int $sort_order
 * @property-read BmiStandard $standard
 * @property-read InterventionPackage|null $interventionPackage
 */
#[Fillable([
    'bmi_standard_id', 'label', 'category', 'min_bmi', 'max_bmi', 'min_age', 'max_age', 'sex',
    'requires_intervention', 'intervention_package_id', 'color', 'sort_order',
])]
class BmiClassification extends Model
{
    /** @use HasFactory<BmiClassificationFactory> */
    use HasFactory, RecordsActivity;

    public const COLORS = ['zinc', 'green', 'lime', 'amber', 'orange', 'red', 'rose', 'sky', 'blue', 'indigo', 'purple'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ClassificationCategory::class,
            'sex' => Sex::class,
            'min_bmi' => 'float',
            'max_bmi' => 'float',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'requires_intervention' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BmiStandard, $this>
     */
    public function standard(): BelongsTo
    {
        return $this->belongsTo(BmiStandard::class, 'bmi_standard_id');
    }

    /**
     * @return BelongsTo<InterventionPackage, $this>
     */
    public function interventionPackage(): BelongsTo
    {
        return $this->belongsTo(InterventionPackage::class);
    }

    public function matches(float $bmi, ?int $age, ?Sex $sex): bool
    {
        if ($this->min_bmi !== null && $bmi < $this->min_bmi) {
            return false;
        }

        if ($this->max_bmi !== null && $bmi >= $this->max_bmi) {
            return false;
        }

        if ($this->sex !== null && $sex !== null && $this->sex !== $sex) {
            return false;
        }

        if ($age !== null && $this->min_age !== null && $age < $this->min_age) {
            return false;
        }

        if ($age !== null && $this->max_age !== null && $age > $this->max_age) {
            return false;
        }

        return true;
    }

    /**
     * Range description such as "18.50 – 24.99" or "≥ 30.00".
     */
    public function rangeLabel(): string
    {
        return match (true) {
            $this->min_bmi === null && $this->max_bmi !== null => '< '.number_format($this->max_bmi, 2),
            $this->max_bmi === null && $this->min_bmi !== null => '≥ '.number_format($this->min_bmi, 2),
            $this->min_bmi !== null && $this->max_bmi !== null => number_format($this->min_bmi, 2).' – '.number_format($this->max_bmi - 0.01, 2),
            default => 'Any',
        };
    }

    public function auditPersonnelId(): ?int
    {
        return null;
    }
}
