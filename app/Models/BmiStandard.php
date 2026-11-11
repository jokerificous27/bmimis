<?php

namespace App\Models;

use App\Enums\StandardType;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksAuthors;
use Carbon\CarbonInterface;
use Database\Factories\BmiStandardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A versioned BMI classification standard (WHO or PNP), effective from a given date.
 *
 * @property int $id
 * @property StandardType $type
 * @property string $name
 * @property string|null $reference
 * @property string|null $description
 * @property CarbonInterface $effective_from
 * @property CarbonInterface|null $effective_to
 * @property bool $is_active
 * @property bool $is_provisional
 */
#[Fillable(['type', 'name', 'reference', 'description', 'effective_from', 'effective_to', 'is_active', 'is_provisional'])]
class BmiStandard extends Model
{
    /** @use HasFactory<BmiStandardFactory> */
    use HasFactory, RecordsActivity, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StandardType::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
            'is_provisional' => 'boolean',
        ];
    }

    /**
     * @return HasMany<BmiClassification, $this>
     */
    public function classifications(): HasMany
    {
        return $this->hasMany(BmiClassification::class)->orderBy('sort_order')->orderBy('min_bmi');
    }

    /**
     * Standards of a type that are in force on the given date, newest first.
     *
     * @param  Builder<self>  $query
     */
    public function scopeEffectiveOn(Builder $query, StandardType $type, CarbonInterface $date): void
    {
        $query->where('type', $type->value)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }

    public function auditPersonnelId(): ?int
    {
        return null;
    }
}
