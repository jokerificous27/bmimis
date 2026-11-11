<?php

namespace App\Livewire\Concerns;

use App\Models\MonthlyWeightMonitoring;

/**
 * Chart.js configurations built from live statistics.
 */
trait BuildsCharts
{
    /**
     * @return list<string>
     */
    protected function monthLabels(): array
    {
        return array_map(fn (string $name) => substr($name, 0, 3), array_values(MonthlyWeightMonitoring::MONTHS));
    }

    /**
     * @param  array<string, int>  $distribution
     * @return array<string, mixed>
     */
    protected function doughnutChart(array $distribution): array
    {
        $colors = ['#2f8f5b', '#e0742b', '#c0392b', '#d99a1e', '#b03a5b', '#3b6fb6', '#8a8f98', '#7cb342'];
        $labels = array_keys($distribution);

        return [
            'type' => 'doughnut',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'data' => array_values($distribution),
                    'backgroundColor' => array_map(fn (string $label, int $index) => $this->colorForLabel($label) ?? $colors[$index % count($colors)], $labels, array_keys($labels)),
                    'borderWidth' => 0,
                ]],
            ],
            'options' => ['cutout' => '62%', 'interaction' => ['mode' => 'nearest'], 'plugins' => ['legend' => ['position' => 'right']]],
        ];
    }

    /**
     * @param  array<int, float|null>  $trend
     * @return array<string, mixed>
     */
    protected function complianceChart(array $trend, float $target): array
    {
        return [
            'type' => 'bar',
            'data' => [
                'labels' => $this->monthLabels(),
                'datasets' => [
                    ['type' => 'bar', 'label' => __('Compliance %'), 'data' => array_values($trend), 'backgroundColor' => '#1d3461', 'borderRadius' => 4],
                    ['type' => 'line', 'label' => __('Target'), 'data' => array_fill(0, 12, $target), 'borderColor' => '#2f8f5b', 'borderDash' => [6, 4], 'pointRadius' => 0],
                ],
            ],
            'options' => ['scales' => ['y' => ['min' => 0, 'max' => 100, 'ticks' => ['stepSize' => 20]]]],
        ];
    }

    /**
     * @param  array<int, array{bmi: float|null, weight: float|null, count: int}>  $averages
     * @param  array<int, array{bmi: float|null, weight: float|null, count: int}>|null  $previous
     * @return array<string, mixed>
     */
    protected function averageChart(array $averages, string $metric, string $label, ?array $previous = null, ?string $previousLabel = null): array
    {
        $datasets = [[
            'label' => $label, 'data' => array_values(array_map(fn ($row) => $row[$metric], $averages)),
            'borderColor' => '#1d3461', 'backgroundColor' => 'rgba(29,52,97,.10)', 'fill' => true, 'tension' => .3, 'spanGaps' => true,
        ]];

        if ($previous !== null) {
            $datasets[] = [
                'label' => $previousLabel, 'data' => array_values(array_map(fn ($row) => $row[$metric], $previous)),
                'borderColor' => '#8a8f98', 'borderDash' => [5, 4], 'tension' => .3, 'spanGaps' => true, 'pointRadius' => 2,
            ];
        }

        return ['type' => 'line', 'data' => ['labels' => $this->monthLabels(), 'datasets' => $datasets]];
    }

    /**
     * @param  array<string, array{below: int, normal: int, above: int, none: int}>  $units
     * @return array<string, mixed>
     */
    protected function unitDistributionChart(array $units): array
    {
        $series = [
            'below' => [__('Below normal'), '#d99a1e'],
            'normal' => [__('Normal'), '#2f8f5b'],
            'above' => [__('Above normal'), '#c0392b'],
            'none' => [__('No assessment'), '#c9ccd1'],
        ];

        return [
            'type' => 'bar',
            'data' => [
                'labels' => array_keys($units),
                'datasets' => collect($series)->map(fn ($meta, $key) => [
                    'label' => $meta[0], 'data' => array_values(array_map(fn ($counts) => $counts[$key], $units)), 'backgroundColor' => $meta[1],
                ])->values()->all(),
            ],
            'options' => ['indexAxis' => 'y', 'scales' => ['x' => ['stacked' => true, 'ticks' => ['precision' => 0]], 'y' => ['stacked' => true]]],
        ];
    }

    /**
     * @param  array<string, int>  $distribution
     * @return array<string, mixed>
     */
    protected function barChart(array $distribution, string $label, string $color = '#3b6fb6'): array
    {
        return [
            'type' => 'bar',
            'data' => ['labels' => array_keys($distribution), 'datasets' => [['label' => $label, 'data' => array_values($distribution), 'backgroundColor' => $color, 'borderRadius' => 4]]],
            'options' => ['plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['ticks' => ['precision' => 0]]]],
        ];
    }

    private function colorForLabel(string $label): ?string
    {
        $label = strtolower($label);

        return match (true) {
            str_contains($label, 'normal') => '#2f8f5b',
            str_contains($label, 'under') => '#d99a1e',
            str_contains($label, 'pre-obese'), str_contains($label, 'overweight') => '#e0742b',
            str_contains($label, 'iii') => '#7f1d1d',
            str_contains($label, 'ii') => '#991b1b',
            str_contains($label, 'obese') => '#c0392b',
            str_contains($label, 'unclassified') => '#c9ccd1',
            default => null,
        };
    }
}
