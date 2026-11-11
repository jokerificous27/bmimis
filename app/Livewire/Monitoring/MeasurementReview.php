<?php

namespace App\Livewire\Monitoring;

use App\Livewire\Concerns\ValidatesMeasurements;
use App\Models\MonthlyWeightMonitoring;
use App\Services\MeasurementReviewService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Shown where a monthly weigh-in changed the BMI a lot since the last assessment: asks the Health
 * Service staff who encode assessments whether to update waist, hip and wrist too. Updating is optional.
 */
class MeasurementReview extends Component
{
    use ValidatesMeasurements;

    #[Locked]
    public int $recordId;

    public bool $editing = false;

    public string $waist_cm = '';

    public string $hip_cm = '';

    public string $wrist_cm = '';

    /**
     * What the administrator decided in this session ("updated" or "declined"), shown as confirmation.
     */
    #[Locked]
    public ?string $outcome = null;

    #[Locked]
    public ?int $assessmentId = null;

    public function mount(int $recordId): void
    {
        $this->recordId = $recordId;
        $this->authorize('view', $this->record);
    }

    #[Computed]
    public function record(): MonthlyWeightMonitoring
    {
        return MonthlyWeightMonitoring::query()
            ->with(['personnel.rank:id,abbreviation', 'baselineAssessment'])
            ->findOrFail($this->recordId);
    }

    #[Computed]
    public function canReview(): bool
    {
        return Auth::user()->can('reviewMeasurements', $this->record);
    }

    /**
     * The administrator wants to update: open the form with the last measurements filled in, so
     * anything left unchanged is carried over.
     */
    public function startUpdate(): void
    {
        $this->authorize('reviewMeasurements', $this->record);

        $baseline = $this->record->baselineAssessment;
        $this->waist_cm = $this->decimal($baseline?->waist_cm);
        $this->hip_cm = $this->decimal($baseline?->hip_cm);
        $this->wrist_cm = $this->decimal($baseline?->wrist_cm);
        $this->resetValidation();
        $this->editing = true;
    }

    public function cancelUpdate(): void
    {
        $this->editing = false;
        $this->resetValidation();
    }

    public function save(MeasurementReviewService $reviews): void
    {
        $this->authorize('reviewMeasurements', $this->record);

        $validated = $this->validate([
            'waist_cm' => $this->measurementRule('waist', required: false),
            'hip_cm' => $this->measurementRule('hip', required: false),
            'wrist_cm' => $this->measurementRule('wrist', required: false),
        ], $this->measurementMessages(), ['waist_cm' => 'waist', 'hip_cm' => 'hip', 'wrist_cm' => 'wrist']);

        $measurements = array_map(fn ($value) => filled($value) ? (float) $value : null, $validated);

        if (array_filter($measurements) === []) {
            throw ValidationException::withMessages(['waist_cm' => __('Enter at least one measurement, or choose "No update needed".')]);
        }

        $assessment = $reviews->update($this->record, $measurements);

        $this->finish('updated', $assessment->id);
        Flux::toast(variant: 'success', text: __('Measurements saved as a new assessment for :name.', ['name' => $this->record->personnel->rank_name]));
    }

    public function decline(MeasurementReviewService $reviews): void
    {
        $this->authorize('reviewMeasurements', $this->record);

        $reviews->decline($this->record);

        $this->finish('declined');
        Flux::toast(text: __('Noted: no measurement update for :name.', ['name' => $this->record->personnel->rank_name]));
    }

    private function finish(string $outcome, ?int $assessmentId = null): void
    {
        $this->outcome = $outcome;
        $this->assessmentId = $assessmentId;
        $this->editing = false;
        unset($this->record);
        $this->dispatch('measurement-review-answered');
    }

    private function decimal(?float $value): string
    {
        return $value === null ? '' : number_format($value, 1, '.', '');
    }

    public function render(): View
    {
        return view('livewire.monitoring.measurement-review');
    }
}
