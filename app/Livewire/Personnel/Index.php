<?php

namespace App\Livewire\Personnel;

use App\Enums\ClassificationCategory;
use App\Enums\PersonnelStatus;
use App\Livewire\Concerns\WithLookups;
use App\Models\Personnel;
use App\Services\PersonnelQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Personnel Directory')]
class Index extends Component
{
    use WithLookups, WithPagination;

    #[Url(except: 'directory')]
    public string $mode = 'directory';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $unitId = '';

    #[Url(except: '')]
    public string $rankId = '';

    #[Url(except: '')]
    public string $sex = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: 'last_name')]
    public string $sortBy = 'last_name';

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->authorize('viewAny', Personnel::class);

        $this->mode = in_array($this->mode, ['directory', 'profiles'], true) ? $this->mode : 'directory';
    }

    public function updating(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function sort(string $column): void
    {
        if (! in_array($column, ['last_name', 'employee_number', 'unit_id', 'rank_id'], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'unitId', 'rankId', 'sex', 'category');
        $this->status = 'active';
        $this->resetPage();
    }

    public function render(): View
    {
        $user = Auth::user();

        $personnel = PersonnelQuery::for($user, ['unit_id' => $this->unitId, 'rank_id' => $this->rankId, 'sex' => $this->sex, 'search' => $this->search])
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->category !== '', function (Builder $query): void {
                $this->category === 'none'
                    ? $query->whereDoesntHave('latestAssessment')
                    : $query->whereHas('latestAssessment', fn (Builder $query) => $query->where('pnp_category', $this->category));
            })
            ->with(['rank:id,abbreviation', 'unit:id,code,name', 'user:id,is_active', 'latestAssessment', 'activeIntervention.package:id,code',
                'monthlyWeights' => fn ($query) => $query->where('year', today()->year)->where('month', today()->month)])
            ->orderBy($this->sortBy, $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->orderBy('first_name')
            ->paginate(15);

        return view('livewire.personnel.index', [
            'personnel' => $personnel,
            'statuses' => PersonnelStatus::options(),
            'categories' => ClassificationCategory::options(),
        ])->title($this->mode === 'profiles' ? __('BMI Profiles') : __('Personnel Directory'));
    }
}
