<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\RankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $abbreviation
 * @property string $name
 * @property string $category
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['abbreviation', 'name', 'category', 'sort_order', 'is_active'])]
class Rank extends Model
{
    /** @use HasFactory<RankFactory> */
    use HasFactory, RecordsActivity;

    public const CATEGORIES = ['Commissioned Officer', 'Non-Commissioned Officer', 'Non-Uniformed Personnel'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Personnel, $this>
     */
    public function personnel(): HasMany
    {
        return $this->hasMany(Personnel::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('abbreviation');
    }

    public function auditPersonnelId(): ?int
    {
        return null;
    }
}
