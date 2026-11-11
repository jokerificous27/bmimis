<?php

namespace App\Livewire\Interventions;

use App\Enums\InterventionStatus;
use App\Models\PersonnelIntervention;
use App\Services\InterventionService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Intervention')]
class Show extends Component
{
    public PersonnelIntervention $intervention;

    public string $followUpOn = '';

    public string $followUpWeight = '';

    public string $followUpNotes = '';

    public string $nextFollowUpOn = '';

    public string $closeStatus = 'completed';

    public string $closedOn = '';

    public string $outcome = '';

    public string $healthServiceNotes = '';

    public string $remarks = '';

    public function mount(PersonnelIntervention $intervention): void
    {
        $this->authorize('view', $intervention);

        $this->intervention = $intervention;
        $this->followUpOn = today()->format('Y-m-d');
        $this->closedOn = today()->format('Y-m-d');
        $this->healthServiceNotes = (string) $intervention->health_service_notes;
        $this->remarks = (string) $intervention->remarks;
        $interval = $intervention->package->follow_up_interval_days ?: 30;
        $this->nextFollowUpOn = today()->addDays($interval)->format('Y-m-d');
    }

    public function recordFollowUp(InterventionService $service): void
    {
        $this->authorize('update', $this->intervention);

        $this->validate([
            'followUpOn' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$this->intervention->assigned_on->format('Y-m-d')],
            'followUpWeight' => ['nullable', 'numeric', 'min:30', 'max:250', 'decimal:0,2'],
            'followUpNotes' => ['nullable', 'string', 'max:2000'],
            'nextFollowUpOn' => ['nullable', 'date', 'after:followUpOn'],
        ], [], ['followUpOn' => 'follow-up date', 'nextFollowUpOn' => 'next follow-up date']);

        $service->recordFollowUp(
            $this->intervention,
            CarbonImmutable::parse($this->followUpOn),
            $this->followUpWeight !== '' ? (float) $this->followUpWeight : null,
            $this->followUpNotes ?: null,
            $this->nextFollowUpOn ? CarbonImmutable::parse($this->nextFollowUpOn) : null,
        );

        $this->reset('followUpWeight', 'followUpNotes');
        Flux::modal('record-follow-up')->close();
        Flux::toast(variant: 'success', text: __('Follow-up recorded.'));
    }

    public function close(InterventionService $service): void
    {
        $this->authorize('update', $this->intervention);

        $this->validate([
            'closeStatus' => ['required', Rule::in([InterventionStatus::Completed->value, InterventionStatus::Discontinued->value])],
            'closedOn' => ['required', 'date', 'before_or_equal:today'],
            'outcome' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $service->close($this->intervention, InterventionStatus::from($this->closeStatus), CarbonImmutable::parse($this->closedOn), $this->outcome);

        Flux::modal('close-intervention')->close();
        Flux::toast(variant: 'success', text: __('Intervention closed.'));
    }

    public function saveNotes(): void
    {
        $this->authorize('update', $this->intervention);

        $this->validate([
            'healthServiceNotes' => ['nullable', 'string', 'max:4000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->intervention->update(['health_service_notes' => $this->healthServiceNotes ?: null, 'remarks' => $this->remarks ?: null]);

        Flux::toast(variant: 'success', text: __('Notes saved.'));
    }

    public function render(): View
    {
        $this->intervention->load(['personnel.rank', 'personnel.unit', 'package', 'assessment', 'assigner:id,name', 'followUps.recorder:id,name']);

        $weights = $this->intervention->followUps->sortBy('follow_up_on')->filter(fn ($f) => $f->weight_kg !== null);

        return view('livewire.interventions.show', [
            'chart' => [
                'type' => 'line',
                'data' => [
                    'labels' => collect([$this->intervention->assigned_on->format('M j')])->merge($weights->map(fn ($f) => $f->follow_up_on->format('M j')))->all(),
                    'datasets' => array_values(array_filter([
                        ['label' => __('Weight (kg)'), 'data' => collect([$this->intervention->starting_weight_kg])->merge($weights->pluck('weight_kg'))->all(), 'borderColor' => '#1d3461', 'tension' => .3, 'spanGaps' => true],
                        $this->intervention->target_weight_kg ? ['label' => __('Target'), 'data' => array_fill(0, $weights->count() + 1, $this->intervention->target_weight_kg), 'borderColor' => '#2f8f5b', 'borderDash' => [6, 4], 'pointRadius' => 0] : null,
                    ])),
                ],
            ],
            'canManage' => auth()->user()->can('update', $this->intervention),
        ])->title(__('Intervention — :name', ['name' => $this->intervention->personnel->rank_name]));
    }
}
