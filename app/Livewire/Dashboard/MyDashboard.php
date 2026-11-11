<?php

namespace App\Livewire\Dashboard;

use App\Livewire\Concerns\BuildsCharts;
use App\Services\MonthlyMonitoringService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Personal dashboard of a PNP personnel account. Only the signed-in personnel's own records are loaded.
 */
#[Title('My Dashboard')]
class MyDashboard extends Component
{
    use BuildsCharts;

    public function render(MonthlyMonitoringService $monitoring): View
    {
        $user = Auth::user();
        $personnel = $user->personnel?->load(['rank', 'unit', 'activeIntervention.package']);

        if (! $personnel) {
            return view('livewire.personnel.no-profile')->title(__('My Dashboard'));
        }

        $year = (int) today()->format('Y');
        $sheet = $monitoring->yearSheet($personnel, $year);
        $latest = $personnel->latestAssessment()->with('interventionPackage')->first();
        $thisMonth = $sheet->firstWhere('month', (int) today()->format('n'));
        $due = $sheet->whereIn('status', ['recorded', 'missing', 'pending']);

        return view('livewire.dashboard.my', [
            'personnel' => $personnel,
            'qrPayload' => $personnel->qrPayload(),
            'latest' => $latest,
            'thisMonth' => $thisMonth,
            'deadline' => $monitoring->deadlineFor($year, (int) today()->format('n')),
            'recordedCount' => $due->where('status', 'recorded')->count(),
            'dueCount' => $due->count(),
            'chart' => [
                'type' => 'line',
                'data' => [
                    'labels' => $this->monthLabels(),
                    'datasets' => array_values(array_filter([
                        ['label' => __('My weight (kg)'), 'data' => $sheet->map(fn ($row) => $row['record']?->weight_kg)->all(), 'borderColor' => '#1d3461', 'backgroundColor' => 'rgba(29,52,97,.12)', 'fill' => true, 'tension' => .3, 'spanGaps' => true, 'pointRadius' => 4],
                        $latest?->ibw_max_kg ? ['label' => __('Upper ideal weight'), 'data' => array_fill(0, 12, $latest->ibw_max_kg), 'borderColor' => '#c0392b', 'borderDash' => [6, 4], 'pointRadius' => 0] : null,
                        $latest?->ibw_min_kg ? ['label' => __('Lower ideal weight'), 'data' => array_fill(0, 12, $latest->ibw_min_kg), 'borderColor' => '#2f8f5b', 'borderDash' => [6, 4], 'pointRadius' => 0] : null,
                    ])),
                ],
            ],
            'notifications' => $user->notifications()->latest()->limit(5)->get(),
            'year' => $year,
        ]);
    }
}
