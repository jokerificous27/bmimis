<?php

namespace App\Livewire\Reports;

use App\Enums\ClassificationCategory;
use App\Livewire\Concerns\WithLookups;
use App\Models\Personnel;
use App\Services\Reports\ReportCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Reports')]
class Index extends Component
{
    use WithLookups;

    #[Url(except: 'individual')]
    public string $category = 'individual';

    #[Url(except: '')]
    public string $report = '';

    public int $year = 0;

    public int $month = 0;

    public string $unitId = '';

    public string $classification = '';

    public ?int $personnelId = null;

    public string $personnelSearch = '';

    public function mount(): void
    {
        $this->category = array_key_exists($this->category, ReportCatalog::categories()) ? $this->category : 'individual';
        $this->year = (int) today()->format('Y');
        $this->month = (int) today()->format('n');

        if (! ReportCatalog::find($this->report) || ReportCatalog::find($this->report)['category'] !== $this->category) {
            $this->report = (string) array_key_first(ReportCatalog::inCategory($this->category));
        }
    }

    public function updatedCategory(): void
    {
        $this->report = (string) array_key_first(ReportCatalog::inCategory($this->category));
    }

    public function selectPersonnel(int $id): void
    {
        $this->personnelId = Personnel::query()->visibleTo(Auth::user())->findOrFail($id)->id;
        $this->personnelSearch = '';
    }

    /**
     * @return Collection<int, Personnel>
     */
    #[Computed]
    public function personnelResults(): Collection
    {
        if (mb_strlen(trim($this->personnelSearch)) < 2) {
            return collect();
        }

        return Personnel::query()->visibleTo(Auth::user())->search($this->personnelSearch)->with('rank:id,abbreviation')->limit(8)->get();
    }

    #[Computed]
    public function selectedPersonnel(): ?Personnel
    {
        return $this->personnelId ? Personnel::query()->visibleTo(Auth::user())->with('rank')->find($this->personnelId) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function queryParameters(): array
    {
        $filters = ReportCatalog::find($this->report)['filters'] ?? [];

        return array_filter([
            'year' => in_array('year', $filters, true) ? $this->year : null,
            'month' => in_array('month', $filters, true) ? $this->month : null,
            'unit_id' => in_array('unit_id', $filters, true) ? $this->unitId : null,
            'category' => in_array('category', $filters, true) ? $this->classification : null,
            'personnel_id' => in_array('personnel_id', $filters, true) ? $this->personnelId : null,
        ], fn ($value) => filled($value));
    }

    public function render(): View
    {
        $definition = ReportCatalog::find($this->report);
        $missingRequirement = collect($definition['requires'] ?? [])->contains(fn ($field) => blank($this->queryParameters()[$field] ?? null));

        return view('livewire.reports.index', [
            'categories' => ReportCatalog::categories(),
            'reports' => ReportCatalog::inCategory($this->category),
            'definition' => $definition,
            'classifications' => ClassificationCategory::options(),
            'missingRequirement' => $missingRequirement,
            'printUrl' => $missingRequirement ? null : route('reports.print', ['report' => $this->report] + $this->queryParameters()),
            'exportUrl' => $missingRequirement || ReportCatalog::isForm($this->report) || ! Auth::user()->can('records.export') ? null : route('reports.export', ['report' => $this->report] + $this->queryParameters()),
        ])->title(ReportCatalog::categories()[$this->category]);
    }
}
