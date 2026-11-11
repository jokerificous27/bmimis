<?php

namespace App\Services;

use App\Models\Personnel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the authorized, filtered personnel query shared by dashboards, compliance and reports.
 */
class PersonnelQuery
{
    /**
     * @param  array{unit_id?: int|string|null, rank_id?: int|string|null, sex?: string|null, search?: string|null}  $filters
     * @return Builder<Personnel>
     */
    public static function for(User $user, array $filters = []): Builder
    {
        return Personnel::query()
            ->visibleTo($user)
            ->when($filters['unit_id'] ?? null, fn (Builder $query, $unitId) => $query->where('unit_id', $unitId))
            ->when($filters['rank_id'] ?? null, fn (Builder $query, $rankId) => $query->where('rank_id', $rankId))
            ->when($filters['sex'] ?? null, fn (Builder $query, $sex) => $query->where('sex', $sex))
            ->when($filters['search'] ?? null, fn (Builder $query, $search) => $query->search($search));
    }
}
