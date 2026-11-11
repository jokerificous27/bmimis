<?php

namespace App\Livewire\Dashboard;

use App\Enums\ClassificationCategory;
use App\Enums\Sex;
use App\Enums\StandardType;
use App\Livewire\Concerns\BuildsCharts;
use App\Livewire\Concerns\WithLookups;
use App\Models\BmiStandard;
use App\Models\MonthlyWeightMonitoring;
use App\Services\BmiStatistics;
use App\Services\HealthAlerts;
use App\Services\MonthlyMonitoringService;
use App\Services\PersonnelQuery;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Health Service Dashboard')]
class HealthServiceDashboard extends Component
{
    use BuildsCharts, WithLookups;

    #[Url]
    public int $year = 0;

    #[Url]
    public int $month = 0;

    #[Url(except: '')]
    public string $unitId = '';

    #[Url(except: '')]
    public string $rankId = '';

    #[Url(except: '')]
    public string $sex = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $compliance = '';

    public function mount(): void
    {
        $this->year = $this->year ?: (int) today()->format('Y');
        $this->month = $this->month ?: (int) today()->format('n');
    }

    public function resetFilters(): void
    {
        $this->reset('unitId', 'rankId', 'sex', 'category', 'compliance');
        $this->year = (int) today()->format('Y');
        $this->month = (int) today()->format('n');
    }

    public function render(BmiStatistics $statistics, HealthAlerts $alerts, Settings $settings, MonthlyMonitoringService $monitoring): View
    {
        $user = Auth::user();
        $personnel = PersonnelQuery::for($user, ['unit_id' => $this->unitId, 'rank_id' => $this->rankId, 'sex' => $this->sex]);

        $data = $statistics->dashboard($personnel, $this->year, $this->month, $this->category ?: null, $this->compliance ?: null);
        $target = $settings->float('compliance_target_percent');

        return view('livewire.dashboard.health-service', [
            'stats' => $data['stats'],
            'charts' => [
                'pnp' => $this->doughnutChart($data['charts']['pnp_distribution']),
                'who' => $this->doughnutChart($data['charts']['who_distribution']),
                'compliance' => $this->complianceChart($data['charts']['compliance_trend'], $target),
                'bmi' => $this->averageChart($data['charts']['monthly_averages'], 'bmi', __('Average BMI :year', ['year' => $this->year]), $data['charts']['previous_year_averages'], __('Average BMI :year', ['year' => $this->year - 1])),
                'weight' => $this->averageChart($data['charts']['monthly_averages'], 'weight', __('Average weight :year (kg)', ['year' => $this->year]), $data['charts']['previous_year_averages'], (string) ($this->year - 1)),
                'units' => $this->unitDistributionChart($data['charts']['unit_distribution']),
                'interventions' => $this->barChart($data['charts']['intervention_distribution'], __('Ongoing interventions'), '#2f8f5b'),
            ],
            'alerts' => $alerts->all($user, $personnel, (int) today()->format('Y'), (int) today()->format('n')),
            'target' => $target,
            'deadline' => $monitoring->deadlineFor($this->year, $this->month),
            'monthName' => MonthlyWeightMonitoring::MONTHS[$this->month],
            'categories' => ClassificationCategory::options(),
            'sexes' => Sex::options(),
            'provisionalPnp' => BmiStandard::query()->effectiveOn(StandardType::Pnp, today())->first()?->is_provisional ?? true,
        ]);
    }
}
