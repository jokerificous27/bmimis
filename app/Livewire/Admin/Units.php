<?php

namespace App\Livewire\Admin;

use App\Models\Unit;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Unit Management')]
class Units extends Component
{
    #[Url(except: '')]
    public string $search = '';

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'code', 'name', 'description');
        $this->is_active = true;
        Flux::modal('unit-form')->show();
    }

    public function edit(int $id): void
    {
        $unit = Unit::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $unit->id;
        $this->code = $unit->code;
        $this->name = $unit->name;
        $this->description = (string) $unit->description;
        $this->is_active = $unit->is_active;
        Flux::modal('unit-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\s]+$/', Rule::unique('units', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['description'] = $validated['description'] ?: null;

        $this->editingId ? Unit::findOrFail($this->editingId)->update($validated) : Unit::create($validated);

        Flux::modal('unit-form')->close();
        Flux::toast(variant: 'success', text: __('Unit saved.'));
    }

    /**
     * Units with personnel or history cannot be removed; deactivate them instead.
     */
    public function delete(int $id): void
    {
        $unit = Unit::withCount('personnel')->findOrFail($id);

        if ($unit->personnel_count > 0 || $unit->representatives()->exists()) {
            throw ValidationException::withMessages(['delete' => __('This unit still has personnel or representatives. Transfer them or deactivate the unit instead.')]);
        }

        $unit->delete();
        Flux::toast(text: __('Unit removed. Historical records keep their reference to it.'));
    }

    public function render(): View
    {
        return view('livewire.admin.units', [
            'units' => Unit::query()
                ->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))
                ->withCount(['personnel', 'personnel as active_personnel_count' => fn ($q) => $q->active(), 'representatives'])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
