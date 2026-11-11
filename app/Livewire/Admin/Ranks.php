<?php

namespace App\Livewire\Admin;

use App\Models\Rank;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Rank Management')]
class Ranks extends Component
{
    public ?int $editingId = null;

    public string $abbreviation = '';

    public string $name = '';

    public string $category = 'Non-Commissioned Officer';

    public int $sort_order = 0;

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'abbreviation', 'name');
        $this->category = 'Non-Commissioned Officer';
        $this->sort_order = (int) Rank::max('sort_order') + 1;
        $this->is_active = true;
        Flux::modal('rank-form')->show();
    }

    public function edit(int $id): void
    {
        $rank = Rank::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $rank->id;
        $this->abbreviation = $rank->abbreviation;
        $this->name = $rank->name;
        $this->category = $rank->category;
        $this->sort_order = $rank->sort_order;
        $this->is_active = $rank->is_active;
        Flux::modal('rank-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'abbreviation' => ['required', 'string', 'max:20', Rule::unique('ranks', 'abbreviation')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(Rank::CATEGORIES)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ]);

        $this->editingId ? Rank::findOrFail($this->editingId)->update($validated) : Rank::create($validated);

        Flux::modal('rank-form')->close();
        Flux::toast(variant: 'success', text: __('Rank saved.'));
    }

    public function render(): View
    {
        return view('livewire.admin.ranks', [
            'ranks' => Rank::query()->ordered()->withCount('personnel')->get(),
            'categories' => Rank::CATEGORIES,
        ]);
    }
}
