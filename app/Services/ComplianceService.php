<?php

namespace App\Services;

use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use App\Models\Unit;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Monthly weight monitoring compliance: the share of active personnel with a weight recorded for a month.
 */
class ComplianceService
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  Builder<Personnel>  $personnel  an already-authorized, filtered personnel query
     * @return array{total: int, recorded: int, pending: int, percent: float}
     */
    public function summary(Builder $personnel, int $year, int $month): array
    {
        $total = (clone $personnel)->active()->count();
        $recorded = (clone $personnel)->active()
            ->whereHas('monthlyWeights', fn (Builder $query) => $query->where('year', $year)->where('month', $month))
            ->count();

        return [
            'total' => $total,
            'recorded' => $recorded,
            'pending' => $total - $recorded,
            'percent' => $total > 0 ? round($recorded / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Compliance percentage for every month of a year (null for months that have not started).
     *
     * @param  Builder<Personnel>  $personnel
     * @return array<int, float|null>
     */
    public function monthlyTrend(Builder $personnel, int $year): array
    {
        $ids = (clone $personnel)->active()->pluck('id');
        $total = $ids->count();

        $recordedByMonth = $total === 0 ? collect() : MonthlyWeightMonitoring::query()
            ->where('year', $year)
            ->whereIn('personnel_id', $ids)
            ->selectRaw('month, count(*) as aggregate')
            ->groupBy('month')
            ->pluck('aggregate', 'month');

        return collect(range(1, 12))->mapWithKeys(function (int $month) use ($year, $total, $recordedByMonth) {
            if (CarbonImmutable::create($year, $month, 1)->isAfter(today())) {
                return [$month => null];
            }

            return [$month => $total > 0 ? round(((int) ($recordedByMonth[$month] ?? 0)) / $total * 100, 1) : 0.0];
        })->all();
    }

    /**
     * Per-unit compliance for a month, limited to the units the user may see.
     *
     * @return Collection<int, array{unit: Unit, total: int, recorded: int, missing: int, percent: float, meets_target: bool}>
     */
    public function byUnit(User $user, int $year, int $month, ?int $unitId = null): Collection
    {
        $target = $this->settings->float('compliance_target_percent');

        return Unit::query()
            ->when($user->hasUnitScope(), fn (Builder $query) => $query->whereKey($user->unit_id))
            ->when($unitId, fn (Builder $query) => $query->whereKey($unitId))
            ->withCount([
                'personnel as total' => fn (Builder $query) => $query->active(),
                'personnel as recorded' => fn (Builder $query) => $query->active()->whereHas(
                    'monthlyWeights',
                    fn (Builder $query) => $query->where('year', $year)->where('month', $month),
                ),
            ])
            ->orderBy('name')
            ->get()
            ->filter(fn (Unit $unit) => $unit->total > 0)
            ->map(function (Unit $unit) use ($target) {
                $percent = round($unit->recorded / $unit->total * 100, 1);

                return [
                    'unit' => $unit,
                    'total' => (int) $unit->total,
                    'recorded' => (int) $unit->recorded,
                    'missing' => (int) ($unit->total - $unit->recorded),
                    'percent' => $percent,
                    'meets_target' => $percent >= $target,
                ];
            })
            ->sortBy('percent')
            ->values();
    }

    /**
     * Active personnel without a weight for the month.
     *
     * @param  Builder<Personnel>  $personnel
     * @return Builder<Personnel>
     */
    public function missingQuery(Builder $personnel, int $year, int $month): Builder
    {
        return (clone $personnel)->active()
            ->whereDoesntHave('monthlyWeights', fn (Builder $query) => $query->where('year', $year)->where('month', $month));
    }
}
