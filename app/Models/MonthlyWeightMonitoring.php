<?php

namespace App\Models;

use App\Enums\ClassificationCategory;
use App\Enums\MeasurementReview;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksAuthors;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\MonthlyWeightMonitoringFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A monthly weigh-in. One record per personnel per calendar month.
 *
 * @property int $id
 * @property int $personnel_id
 * @property int|null $unit_id
 * @property int $year
 * @property int $month
 * @property float $weight_kg
 * @property float|null $height_cm
 * @property int|null $height_source_assessment_id
 * @property float|null $bmi
 * @property string|null $pnp_classification
 * @property ClassificationCategory|null $pnp_category
 * @property string|null $who_classification
 * @property ClassificationCategory|null $who_category
 * @property CarbonInterface|null $measured_on
 * @property string|null $remarks
 * @property int|null $recorded_by
 * @property string $capture_method manual, station (QR + photo) or import
 * @property string|null $photo_path private photo of the weigh-in (Weigh-in Station)
 * @property float|null $ai_weight_kg weight read from the photo by AI, before confirmation
 * @property string|null $ai_confidence
 * @property MeasurementReview|null $measurement_review whether waist, hip and wrist should be measured again (large BMI change)
 * @property float|null $bmi_change BMI difference from the baseline assessment when the review was requested
 * @property int|null $baseline_assessment_id
 * @property int|null $measurement_reviewed_by
 * @property CarbonInterface|null $measurement_reviewed_at
 * @property-read BmiAssessment|null $baselineAssessment
 * @property-read Personnel $personnel
 * @property-read User|null $recorder
 */
#[Fillable([
    'personnel_id', 'unit_id', 'year', 'month', 'weight_kg', 'height_cm', 'height_source_assessment_id', 'bmi',
    'pnp_classification', 'pnp_category', 'who_classification', 'who_category', 'measured_on', 'remarks', 'recorded_by',
    'capture_method', 'photo_path', 'ai_weight_kg', 'ai_confidence',
])]
class MonthlyWeightMonitoring extends Model
{
    /** @use HasFactory<MonthlyWeightMonitoringFactory> */
    use HasFactory, RecordsActivity, TracksAuthors;

    public const MONTHS = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'weight_kg' => 'float',
            'height_cm' => 'float',
            'bmi' => 'float',
            'ai_weight_kg' => 'float',
            'pnp_category' => ClassificationCategory::class,
            'who_category' => ClassificationCategory::class,
            'measured_on' => 'date',
            'measurement_review' => MeasurementReview::class,
            'bmi_change' => 'float',
            'measurement_reviewed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<BmiAssessment, $this>
     */
    public function heightSourceAssessment(): BelongsTo
    {
        return $this->belongsTo(BmiAssessment::class, 'height_source_assessment_id');
    }

    /**
     * The assessment whose BMI (and waist, hip, wrist) this weigh-in was compared with.
     *
     * @return BelongsTo<BmiAssessment, $this>
     */
    public function baselineAssessment(): BelongsTo
    {
        return $this->belongsTo(BmiAssessment::class, 'baseline_assessment_id');
    }

    public function needsMeasurementReview(): bool
    {
        return $this->measurement_review === MeasurementReview::Pending;
    }

    /**
     * The day the weight was taken (the end of the monitoring month when no date was recorded).
     */
    public function measurementDate(): CarbonImmutable
    {
        return $this->measured_on ? CarbonImmutable::parse($this->measured_on) : $this->periodStart()->endOfMonth();
    }

    public function monthName(): string
    {
        return self::MONTHS[$this->month];
    }

    public function periodStart(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, $this->month, 1);
    }

    public function auditName(): string
    {
        return 'monthly_weight';
    }
}
