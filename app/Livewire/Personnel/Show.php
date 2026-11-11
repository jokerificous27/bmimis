<?php

namespace App\Livewire\Personnel;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\BmiAssessment;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use App\Services\MonthlyMonitoringService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('BMI Profile')]
class Show extends Component
{
    public ?Personnel $personnel = null;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    #[Url]
    public int $year = 0;

    #[Locked]
    public bool $isOwnProfile = false;

    public function mount(?Personnel $personnel = null): void
    {
        $user = Auth::user();

        if (! $personnel?->exists) {
            $personnel = $user->personnel;
            $this->isOwnProfile = true;
        }

        if ($personnel) {
            $this->authorize('view', $personnel);
            $this->isOwnProfile = $this->isOwnProfile || $personnel->user_id === $user->id;
        }

        $this->personnel = $personnel;
        $this->year = $this->year ?: (int) today()->format('Y');
        $this->tab = in_array($this->tab, ['overview', 'progress', 'assessments', 'monitoring', 'interventions', 'history'], true) ? $this->tab : 'overview';
    }

    /**
     * Invalidate the current weigh-in QR card (e.g. lost) and issue a new one.
     */
    public function reissueQr(): void
    {
        $this->authorize('update', $this->personnel);

        $this->personnel->regenerateQrToken();

        Flux::toast(variant: 'success', text: __('New QR code issued. Print the new card; the old one no longer works.'));
    }

    #[Computed]
    public function latest(): ?BmiAssessment
    {
        return $this->personnel?->latestAssessment()->with(['interventionPackage', 'certifier'])->first();
    }

    /**
     * @return Collection<int, BmiAssessment>
     */
    #[Computed]
    public function assessments(): Collection
    {
        return $this->personnel->assessments()
            ->when(! Auth::user()->hasPermission(Permission::EncodeAssessments), fn ($query) => $query->official())
            ->with(['encoder:id,name', 'interventionPackage:id,code'])
            ->orderByDesc('assessed_on')->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function sheet(): Collection
    {
        return app(MonthlyMonitoringService::class)->yearSheet($this->personnel, $this->year);
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function availableYears(): array
    {
        $years = $this->personnel->monthlyWeights()->distinct()->orderByDesc('year')->pluck('year')
            ->merge($this->personnel->assessments()->official()->get(['assessed_on'])->map(fn ($a) => (int) $a->assessed_on->format('Y')))
            ->push((int) today()->format('Y'))
            ->unique()->sortDesc()->values()->all();

        return $years;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function charts(): array
    {
        $labels = array_map(fn ($name) => substr($name, 0, 3), array_values(MonthlyWeightMonitoring::MONTHS));
        $weights = $this->sheet->map(fn ($row) => $row['record']?->weight_kg)->all();
        $bmis = $this->sheet->map(fn ($row) => $row['record']?->bmi)->all();
        $latest = $this->latest;

        $weightDatasets = [[
            'label' => __('Weight (kg)'), 'data' => $weights, 'borderColor' => '#1d3461', 'backgroundColor' => 'rgba(29,52,97,.12)',
            'fill' => true, 'tension' => .3, 'spanGaps' => true, 'pointRadius' => 4,
        ]];

        if ($latest?->ibw_min_kg !== null) {
            $weightDatasets[] = ['label' => __('IBW minimum'), 'data' => array_fill(0, 12, $latest->ibw_min_kg), 'borderColor' => '#2f8f5b', 'borderDash' => [6, 4], 'pointRadius' => 0, 'fill' => false];
            $weightDatasets[] = ['label' => __('IBW maximum'), 'data' => array_fill(0, 12, $latest->ibw_max_kg), 'borderColor' => '#c0392b', 'borderDash' => [6, 4], 'pointRadius' => 0, 'fill' => false];
        }

        $official = $this->personnel->assessments()->official()->orderBy('assessed_on')->get(['assessed_on', 'bmi', 'waist_cm', 'hip_cm', 'weight_kg']);

        $comparison = $this->personnel->monthlyWeights()->orderBy('year')->get(['year', 'month', 'weight_kg'])->groupBy('year');
        $colors = ['#1d3461', '#2f8f5b', '#d99a1e', '#3b6fb6', '#b03a5b', '#8a8f98'];

        return [
            'weight' => [
                'type' => 'line',
                'data' => ['labels' => $labels, 'datasets' => $weightDatasets],
                'options' => ['scales' => ['y' => ['title' => ['display' => true, 'text' => 'kg']]]],
            ],
            'monthlyBmi' => [
                'type' => 'bar',
                'data' => ['labels' => $labels, 'datasets' => [['label' => __('Monthly BMI'), 'data' => $bmis, 'backgroundColor' => '#3b6fb6', 'borderRadius' => 4]]],
                'options' => ['scales' => ['y' => ['suggestedMin' => 15]]],
            ],
            'bmiTrend' => [
                'type' => 'line',
                'data' => [
                    'labels' => $official->map(fn ($a) => $a->assessed_on->format('M Y'))->all(),
                    'datasets' => [['label' => __('BMI (assessments)'), 'data' => $official->pluck('bmi')->all(), 'borderColor' => '#1d3461', 'tension' => .25, 'pointRadius' => 4]],
                ],
            ],
            'bodyMeasurements' => [
                'type' => 'line',
                'data' => [
                    'labels' => $official->map(fn ($a) => $a->assessed_on->format('M Y'))->all(),
                    'datasets' => [
                        ['label' => __('Waist (cm)'), 'data' => $official->pluck('waist_cm')->all(), 'borderColor' => '#d99a1e', 'tension' => .25, 'spanGaps' => true],
                        ['label' => __('Hip (cm)'), 'data' => $official->pluck('hip_cm')->all(), 'borderColor' => '#2f8f5b', 'tension' => .25, 'spanGaps' => true],
                    ],
                ],
            ],
            'yearComparison' => [
                'type' => 'line',
                'data' => [
                    'labels' => $labels,
                    'datasets' => $comparison->take(-6)->values()->map(fn ($records, $index) => [
                        'label' => (string) $records->first()->year,
                        'data' => collect(range(1, 12))->map(fn ($m) => $records->firstWhere('month', $m)?->weight_kg)->all(),
                        'borderColor' => $colors[$index % count($colors)],
                        'tension' => .3,
                        'spanGaps' => true,
                    ])->all(),
                ],
            ],
        ];
    }

    public function render(): View
    {
        if (! $this->personnel) {
            return view('livewire.personnel.no-profile')->title(__('My BMI Profile'));
        }

        $this->personnel->load(['rank', 'unit', 'user', 'activeIntervention.package']);

        return view('livewire.personnel.show', [
            'interventions' => $this->tab === 'interventions' ? $this->personnel->interventions()->with(['package', 'assigner:id,name'])->get() : collect(),
            'assignments' => $this->tab === 'history' ? $this->personnel->assignments()->with(['unit', 'rank', 'creator:id,name'])->get() : collect(),
            'activity' => $this->tab === 'history' && Auth::user()->hasPermission(Permission::ViewAuditTrail)
                ? ActivityLog::query()->where('personnel_id', $this->personnel->id)->with('user:id,name')->latest('id')->limit(25)->get()
                : collect(),
            'monitoringStatus' => app(MonthlyMonitoringService::class)->periodStatus((int) today()->format('Y'), (int) today()->format('n')),
        ])->title($this->isOwnProfile ? __('My BMI Profile') : $this->personnel->rank_name);
    }
}
