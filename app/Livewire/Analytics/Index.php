<?php

namespace App\Livewire\Analytics;

use App\Livewire\Concerns\BuildsCharts;
use App\Livewire\Concerns\WithLookups;
use App\Services\BmiStatistics;
use App\Services\HealthAlerts;
use App\Services\PersonnelQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('BMI Analytics')]
class Index extends Component
{
    use BuildsCharts, WithLookups;

    #[Url(except: 'comparison')]
    public string $tab = 'comparison';

    #[Url]
    public int $year = 0;

    #[Url(except: '')]
    public string $unitId = '';

    public function mount(): void
    {
        $this->year = $this->year ?: (int) today()->format('Y');
        $this->tab = in_array($this->tab, ['comparison', 'trends', 'quality'], true) ? $this->tab : 'comparison';
    }

    public function render(BmiStatistics $statistics, HealthAlerts $alerts): View
    {
        $personnel = PersonnelQuery::for(Auth::user(), ['unit_id' => $this->unitId]);
        $years = range($this->year - 3, $this->year);

        $comparison = $this->tab === 'comparison' ? $statistics->annualComparison($personnel, $years) : [];

        return view('livewire.analytics.index', [
            'comparison' => $comparison,
            'comparisonChart' => $comparison ? [
                'type' => 'bar',
                'data' => [
                    'labels' => array_map('strval', array_keys($comparison)),
                    'datasets' => [
                        ['label' => __('Below normal'), 'data' => array_column($comparison, 'below'), 'backgroundColor' => '#d99a1e'],
                        ['label' => __('Normal'), 'data' => array_column($comparison, 'normal'), 'backgroundColor' => '#2f8f5b'],
                        ['label' => __('Above normal'), 'data' => array_column($comparison, 'above'), 'backgroundColor' => '#c0392b'],
                    ],
                ],
                'options' => ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'ticks' => ['precision' => 0]]]],
            ] : null,
            'averageChart' => $comparison ? [
                'type' => 'line',
                'data' => [
                    'labels' => array_map('strval', array_keys($comparison)),
                    'datasets' => [['label' => __('Average BMI'), 'data' => array_column($comparison, 'average_bmi'), 'borderColor' => '#1d3461', 'tension' => .3]],
                ],
            ] : null,
            'trends' => $this->tab === 'trends' ? $alerts->significantWeightChanges($personnel) : collect(),
            'outdated' => $this->tab === 'trends' ? $alerts->outdatedAssessments($personnel, $statistics->latestAssessments((clone $personnel)->active(), today()), 50) : collect(),
            'quality' => $this->tab === 'quality' ? $alerts->dataQuality($personnel, $this->year) : [],
        ]);
    }
}
