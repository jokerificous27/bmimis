<?php

namespace App\Livewire\Concerns;

use App\Models\InterventionPackage;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Rank;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * Select options shared by filter bars and forms. Unit options respect the user's unit scope.
 */
trait WithLookups
{
    /**
     * @return Collection<int, Unit>
     */
    #[Computed(persist: true, seconds: 300)]
    public function unitOptions(): Collection
    {
        $user = Auth::user();

        return Unit::query()
            ->when($user->hasUnitScope(), fn (Builder $query) => $query->whereKey($user->unit_id))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_active']);
    }

    /**
     * @return Collection<int, Rank>
     */
    #[Computed(persist: true, seconds: 300)]
    public function rankOptions(): Collection
    {
        return Rank::query()->ordered()->get(['id', 'abbreviation', 'name', 'is_active']);
    }

    /**
     * @return Collection<int, InterventionPackage>
     */
    #[Computed]
    public function packageOptions(): Collection
    {
        return InterventionPackage::query()->active()->orderBy('code')->get(['id', 'code', 'name']);
    }

    /**
     * @return array<int, string>
     */
    public function monthOptions(): array
    {
        return MonthlyWeightMonitoring::MONTHS;
    }

    /**
     * Years available in selectors: from the first recorded year (or five years back) to the current year.
     *
     * @return list<int>
     */
    public function yearOptions(): array
    {
        $current = (int) today()->format('Y');
        $first = (int) (MonthlyWeightMonitoring::query()->min('year') ?? $current - 4);

        return range($current, min($first, $current - 4));
    }
}
