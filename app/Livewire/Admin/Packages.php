<?php

namespace App\Livewire\Admin;

use App\Models\InterventionPackage;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Intervention packages are encoded by the Health Service; the system does not invent official descriptions.
 */
#[Title('Intervention Packages')]
class Packages extends Component
{
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $requirements = '';

    public string $recommended_activity = '';

    public string $nutrition_guidance = '';

    public string $follow_up_interval_days = '30';

    public string $duration_weeks = '';

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'code', 'name', 'description', 'requirements', 'recommended_activity', 'nutrition_guidance', 'duration_weeks');
        $this->follow_up_interval_days = '30';
        $this->is_active = true;
        Flux::modal('package-form')->show();
    }

    public function edit(int $id): void
    {
        $package = InterventionPackage::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $package->id;

        foreach (['code', 'name', 'description', 'requirements', 'recommended_activity', 'nutrition_guidance'] as $field) {
            $this->{$field} = (string) $package->{$field};
        }

        $this->follow_up_interval_days = (string) $package->follow_up_interval_days;
        $this->duration_weeks = (string) $package->duration_weeks;
        $this->is_active = $package->is_active;
        Flux::modal('package-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('intervention_packages', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'requirements' => ['nullable', 'string', 'max:4000'],
            'recommended_activity' => ['nullable', 'string', 'max:4000'],
            'nutrition_guidance' => ['nullable', 'string', 'max:4000'],
            'follow_up_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:104'],
            'is_active' => ['boolean'],
        ]);

        $validated = array_map(fn ($value) => $value === '' ? null : $value, $validated);

        $this->editingId ? InterventionPackage::findOrFail($this->editingId)->update($validated) : InterventionPackage::create($validated);

        Flux::modal('package-form')->close();
        Flux::toast(variant: 'success', text: __('Intervention package saved.'));
    }

    public function render(): View
    {
        return view('livewire.admin.packages', [
            'packages' => InterventionPackage::query()->withCount(['assignments', 'assignments as ongoing_count' => fn ($q) => $q->where('status', 'ongoing')])->orderBy('code')->get(),
        ]);
    }
}
