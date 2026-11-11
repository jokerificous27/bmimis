<?php

namespace App\Livewire\Monitoring;

use App\Enums\MeasurementReview;
use App\Livewire\Concerns\ValidatesMeasurements;
use App\Livewire\Concerns\WithLookups;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use App\Services\MonthlyMonitoringService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Monthly Weight Monitoring')]
class PersonnelYear extends Component
{
    use ValidatesMeasurements, WithLookups;

    public ?Personnel $personnel = null;

    #[Url]
    public int $year = 0;

    public ?int $entryMonth = null;

    public string $entryWeight = '';

    public string $entryMeasuredOn = '';

    public string $entryRemarks = '';

    public ?int $correctingId = null;

    public string $correctWeight = '';

    public string $correctMeasuredOn = '';

    public string $correctReason = '';

    public function mount(?Personnel $personnel = null): void
    {
        if (! $personnel?->exists) {
            $personnel = Auth::user()->personnel;
        }

        if ($personnel) {
            $this->authorize('view', $personnel);
        }

        $this->personnel = $personnel;
        $this->year = $this->year ?: (int) today()->format('Y');
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
     * The weigh-in (any year) with an open question about updating waist, hip and wrist.
     */
    #[Computed]
    public function pendingReviewId(): ?int
    {
        return $this->personnel?->monthlyWeights()
            ->where('measurement_review', MeasurementReview::Pending->value)
            ->orderByDesc('year')->orderByDesc('month')
            ->value('id');
    }

    #[On('measurement-review-answered')]
    public function refreshAfterReview(): void
    {
        unset($this->sheet, $this->pendingReviewId);
    }

    #[Computed]
    public function canEncode(): bool
    {
        return Auth::user()->can('encodeMonitoring', $this->personnel);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function chart(): array
    {
        return [
            'type' => 'line',
            'data' => [
                'labels' => array_map(fn ($name) => substr($name, 0, 3), array_values(MonthlyWeightMonitoring::MONTHS)),
                'datasets' => [[
                    'label' => __('Weight (kg)'),
                    'data' => $this->sheet->map(fn ($row) => $row['record']?->weight_kg)->all(),
                    'borderColor' => '#1d3461', 'backgroundColor' => 'rgba(29,52,97,.12)', 'fill' => true, 'tension' => .3, 'spanGaps' => true, 'pointRadius' => 4,
                ]],
            ],
        ];
    }

    public function openEntry(int $month): void
    {
        $this->authorize('encodeMonitoring', $this->personnel);

        $this->resetValidation();
        $this->entryMonth = $month;
        $this->entryWeight = '';
        $this->entryRemarks = '';
        $period = CarbonImmutable::create($this->year, $month, 1);
        $this->entryMeasuredOn = ($period->isSameMonth(today()) ? today() : $period->endOfMonth()->min(today()))->format('Y-m-d');

        Flux::modal('monthly-entry')->show();
    }

    public function saveEntry(MonthlyMonitoringService $monitoring): void
    {
        $this->authorize('encodeMonitoring', $this->personnel);

        $period = CarbonImmutable::create($this->year, (int) $this->entryMonth, 1);

        $this->validate([
            'entryMonth' => ['required', 'integer', 'between:1,12'],
            'entryWeight' => $this->measurementRule('weight', decimals: 2),
            'entryMeasuredOn' => ['required', 'date', 'after_or_equal:'.$period->format('Y-m-d'), 'before_or_equal:'.$period->endOfMonth()->min(today())->format('Y-m-d')],
            'entryRemarks' => ['nullable', 'string', 'max:500'],
        ], array_merge($this->measurementMessages(), [
            'entryWeight.min' => __('Weight is below the plausible range. Enter kilograms.'),
            'entryWeight.max' => __('Weight is above the plausible range. Enter kilograms, not pounds.'),
            'entryMeasuredOn.after_or_equal' => __('The measurement date must fall within the selected month.'),
            'entryMeasuredOn.before_or_equal' => __('The measurement date must fall within the selected month and cannot be in the future.'),
        ]), ['entryWeight' => 'weight']);

        $monitoring->record($this->personnel, $this->year, (int) $this->entryMonth, (float) $this->entryWeight, CarbonImmutable::parse($this->entryMeasuredOn), $this->entryRemarks ?: null);

        unset($this->sheet, $this->chart);
        Flux::modal('monthly-entry')->close();
        Flux::toast(variant: 'success', text: __('Weight recorded for :month :year.', ['month' => MonthlyWeightMonitoring::MONTHS[$this->entryMonth], 'year' => $this->year]));
    }

    public function openCorrection(int $recordId): void
    {
        $record = $this->findRecord($recordId);
        $this->authorize('update', $record);

        $this->resetValidation();
        $this->correctingId = $record->id;
        $this->correctWeight = number_format($record->weight_kg, 2, '.', '');
        $this->correctMeasuredOn = (string) $record->measured_on?->format('Y-m-d');
        $this->correctReason = '';

        Flux::modal('monthly-correction')->show();
    }

    public function saveCorrection(MonthlyMonitoringService $monitoring): void
    {
        $record = $this->findRecord((int) $this->correctingId);
        $this->authorize('update', $record);

        $this->validate([
            'correctWeight' => $this->measurementRule('weight', decimals: 2),
            'correctMeasuredOn' => ['nullable', 'date', 'before_or_equal:today'],
            'correctReason' => ['required', 'string', 'min:5', 'max:500'],
        ], $this->measurementMessages(), ['correctWeight' => 'weight', 'correctReason' => 'reason for correction']);

        $monitoring->correct($record, (float) $this->correctWeight, $this->correctReason, $this->correctMeasuredOn ? CarbonImmutable::parse($this->correctMeasuredOn) : null);

        unset($this->sheet, $this->chart);
        Flux::modal('monthly-correction')->close();
        Flux::toast(variant: 'success', text: __('Entry corrected. The previous value is preserved in the audit trail.'));
    }

    private function findRecord(int $id): MonthlyWeightMonitoring
    {
        return $this->personnel->monthlyWeights()->findOrFail($id);
    }

    public function render(): View
    {
        if (! $this->personnel) {
            return view('livewire.personnel.no-profile')->title(__('My Monthly Monitoring'));
        }

        $this->personnel->loadMissing(['rank', 'unit']);
        $recorded = $this->sheet->where('status', 'recorded')->count();
        $due = $this->sheet->whereIn('status', ['recorded', 'missing', 'pending'])->count();

        return view('livewire.monitoring.personnel-year', [
            'recorded' => $recorded,
            'due' => $due,
            'applicable' => app(MonthlyMonitoringService::class)->applicableAssessment($this->personnel, $this->year, 12),
        ]);
    }
}
