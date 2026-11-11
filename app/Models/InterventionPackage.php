<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksAuthors;
use Database\Factories\InterventionPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An intervention package as defined by the Health Service (e.g. Package "B").
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $requirements
 * @property string|null $recommended_activity
 * @property string|null $nutrition_guidance
 * @property int|null $follow_up_interval_days
 * @property int|null $duration_weeks
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'description', 'requirements', 'recommended_activity', 'nutrition_guidance', 'follow_up_interval_days', 'duration_weeks', 'is_active'])]
class InterventionPackage extends Model
{
    /** @use HasFactory<InterventionPackageFactory> */
    use HasFactory, RecordsActivity, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'follow_up_interval_days' => 'integer',
            'duration_weeks' => 'integer',
        ];
    }

    /**
     * @return HasMany<PersonnelIntervention, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(PersonnelIntervention::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Label as printed on the monitoring form, e.g. Package "B".
     */
    public function displayName(): string
    {
        return 'Package "'.$this->code.'"';
    }

    public function auditPersonnelId(): ?int
    {
        return null;
    }
}
