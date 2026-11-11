<?php

namespace App\Livewire\Dashboard;

use App\Livewire\Concerns\BuildsCharts;
use App\Livewire\Concerns\WithLookups;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Unit;
use App\Services\BmiStatistics;
use App\Services\HealthAlerts;
use App\Services\MonthlyMonitoringService;
use App\Services\PersonnelQuery;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Unit / office dashboard. Unit Representatives are locked to their assigned unit;
 * Health Service staff can choose any unit.
 */
#[Title('Unit Monitoring')]
class UnitDashboard extends Component
{
    use BuildsCharts, WithLookups, WithPagination;

    #[Url]
    public int $year = 0;

    #[Url]
    public int $month = 0;

    #[Url(except: '')]
    public string $unitId = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $show = '';

    public function mount(): void
    {
        $user = Auth::user();

        if ($user->hasUnitScope()) {
            $this->unitId = (string) $user->unit_id;
        }

        $this->unitId = $this->unitId ?: (string) ($this->unitOptions->first()?->id ?? '');
        $this->year = $this->year ?: (int) today()->format('Y');
        $this->month = $this->month ?: (int) today()->format('n');
    }

    public function updating(string $property, mixed $value): void
    {
        if ($property === 'unitId' && Auth::user()->hasUnitScope()) {
            abort(403);
        }

        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(BmiStatistics $statistics, HealthAlerts $alerts, MonthlyMonitoringService $monitoring, Settings $settings): View
    {
        $user = Auth::user();
        $unit = $this->unitId !== '' ? $this->unitOptions->firstWhere('id', (int) $this->unitId) : null;
        $personnel = PersonnelQuery::for($user, ['unit_id' => $unit?->id ?? -1]);

        $data = $statistics->dashboard($personnel, $this->year, $this->month);
        $latest = $statistics->latestAssessments((clone $personnel)->active(), today());

        $table = (clone $personnel)->active()->search($this->search)
            ->when($this->show === 'pending', fn (Builder $query) => $query->whereDoesntHave('monthlyWeights', fn ($q) => $q->where('year', $this->year)->where('month', $this->month)))
            ->when($this->show === 'follow-up', fn (Builder $query) => $query->whereIn('id', $latest->filter(fn ($a) => $a->requires_intervention)->keys()))
            ->with(['rank:id,abbreviation', 'activeIntervention.package:id,code',
                'monthlyWeights' => fn ($query) => $query->where('year', $this->year)->where('month', $this->month)])
            ->orderBy('last_name')
            ->paginate(15);

        return view('livewire.dashboard.unit', [
            'unit' => $unit instanceof Unit ? $unit : null,
            'stats' => $data['stats'],
            'latest' => $latest,
            'table' => $table,
            'charts' => [
                'pnp' => $this->doughnutChart($data['charts']['pnp_distribution']),
                'compliance' => $this->complianceChart($data['charts']['compliance_trend'], $settings->float('compliance_target_percent')),
            ],
            'followUps' => $alerts->followUpsDue($personnel, 10, 14),
            'deadline' => $monitoring->deadlineFor($this->year, $this->month),
            'periodStatus' => $monitoring->periodStatus($this->year, $this->month),
            'monthName' => MonthlyWeightMonitoring::MONTHS[$this->month],
            'locked' => $user->hasUnitScope(),
        ]);
    }
}
