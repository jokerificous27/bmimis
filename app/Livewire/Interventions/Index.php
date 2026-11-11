<?php

namespace App\Livewire\Interventions;

use App\Enums\InterventionStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\WithLookups;
use App\Models\InterventionPackage;
use App\Models\Personnel;
use App\Models\PersonnelIntervention;
use App\Services\BmiStatistics;
use App\Services\InterventionService;
use App\Services\PersonnelQuery;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Intervention Management')]
class Index extends Component
{
    use WithLookups, WithPagination;

    #[Url(except: 'ongoing')]
    public string $status = 'ongoing';

    #[Url(except: '')]
    public string $packageId = '';

    #[Url(except: '')]
    public string $unitId = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $overdue = false;

    public ?int $assignPersonnelId = null;

    public string $assignSearch = '';

    public string $assignPackageId = '';

    public string $assignedOn = '';

    public string $nextFollowUpOn = '';

    public string $targetWeight = '';

    public string $assignRemarks = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PersonnelIntervention::class);
        $this->assignedOn = today()->format('Y-m-d');

        if ($requested = request()->integer('assign')) {
            $this->openAssign($requested);
        }
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['status', 'packageId', 'unitId', 'search', 'overdue'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function canManage(): bool
    {
        return Auth::user()->hasPermission(Permission::ManageInterventions);
    }

    /**
     * @return Collection<int, Personnel>
     */
    #[Computed]
    public function assignResults(): Collection
    {
        if (mb_strlen(trim($this->assignSearch)) < 2) {
            return collect();
        }

        return Personnel::query()->visibleTo(Auth::user())->active()->search($this->assignSearch)
            ->with(['rank:id,abbreviation', 'unit:id,code'])->limit(8)->get();
    }

    #[Computed]
    public function assignPersonnel(): ?Personnel
    {
        return $this->assignPersonnelId ? Personnel::query()->visibleTo(Auth::user())->with(['rank', 'latestAssessment'])->find($this->assignPersonnelId) : null;
    }

    public function openAssign(?int $personnelId = null): void
    {
        abort_unless($this->canManage, 403);

        $this->resetValidation();
        $this->reset('assignSearch', 'assignPackageId', 'targetWeight', 'assignRemarks', 'nextFollowUpOn');
        $this->assignPersonnelId = null;
        $this->assignedOn = today()->format('Y-m-d');

        if ($personnelId) {
            $this->chooseAssignPersonnel($personnelId);
        }

        Flux::modal('assign-intervention')->show();
    }

    public function chooseAssignPersonnel(int $personnelId): void
    {
        $person = Personnel::query()->visibleTo(Auth::user())->with('latestAssessment')->findOrFail($personnelId);
        $this->authorize('manageInterventions', $person);

        $this->assignPersonnelId = $person->id;
        $this->assignSearch = '';
        $this->assignPackageId = (string) ($person->latestAssessment?->intervention_package_id ?? '');
        unset($this->assignPersonnel);
    }

    public function updatedAssignPackageId(): void
    {
        $package = InterventionPackage::find($this->assignPackageId);

        if ($package?->follow_up_interval_days && $this->assignedOn) {
            $this->nextFollowUpOn = CarbonImmutable::parse($this->assignedOn)->addDays($package->follow_up_interval_days)->format('Y-m-d');
        }
    }

    public function assign(InterventionService $service): void
    {
        $person = $this->assignPersonnel ?? abort(404);
        $this->authorize('manageInterventions', $person);

        $this->validate([
            'assignPersonnelId' => ['required', 'integer'],
            'assignPackageId' => ['required', Rule::exists('intervention_packages', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'assignedOn' => ['required', 'date', 'before_or_equal:today'],
            'nextFollowUpOn' => ['nullable', 'date', 'after_or_equal:assignedOn'],
            'targetWeight' => ['nullable', 'numeric', 'min:30', 'max:250'],
            'assignRemarks' => ['nullable', 'string', 'max:1000'],
        ], [], ['assignPackageId' => 'package', 'assignPersonnelId' => 'personnel']);

        $intervention = $service->assign(
            $person,
            InterventionPackage::findOrFail($this->assignPackageId),
            CarbonImmutable::parse($this->assignedOn),
            $this->nextFollowUpOn ? CarbonImmutable::parse($this->nextFollowUpOn) : null,
            $person->latestAssessment,
            $this->targetWeight !== '' ? (float) $this->targetWeight : null,
            $this->assignRemarks ?: null,
        );

        Flux::modal('assign-intervention')->close();
        Flux::toast(variant: 'success', text: __('Intervention assigned. The personnel has been notified.'));

        $this->redirectRoute('interventions.show', $intervention, navigate: true);
    }

    public function render(BmiStatistics $statistics): View
    {
        $user = Auth::user();
        $personnelScope = PersonnelQuery::for($user, ['unit_id' => $this->unitId, 'search' => $this->search]);

        $interventions = PersonnelIntervention::query()
            ->whereIn('personnel_id', (clone $personnelScope)->select('personnel.id'))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->packageId !== '', fn (Builder $query) => $query->where('intervention_package_id', $this->packageId))
            ->when($this->overdue, fn (Builder $query) => $query->where('status', InterventionStatus::Ongoing->value)->whereDate('next_follow_up_on', '<', today()))
            ->with(['personnel.rank:id,abbreviation', 'personnel.unit:id,code', 'package:id,code,name'])
            ->orderByRaw('next_follow_up_on is null')
            ->orderBy('next_follow_up_on')
            ->paginate(15);

        $latest = $statistics->latestAssessments((clone $personnelScope)->active(), today());
        $withOngoing = PersonnelIntervention::query()->where('status', InterventionStatus::Ongoing->value)->pluck('personnel_id')->flip();
        $unassignedIds = $latest->filter(fn ($assessment) => $assessment->requires_intervention && ! $withOngoing->has($assessment->personnel_id))->keys();

        return view('livewire.interventions.index', [
            'interventions' => $interventions,
            'statuses' => InterventionStatus::cases(),
            'unassigned' => Personnel::query()->whereIn('id', $unassignedIds)->with('rank:id,abbreviation')->orderBy('last_name')->limit(10)->get()
                ->each(fn ($person) => $person->setRelation('latestAssessment', $latest->get($person->id))),
            'unassignedCount' => $unassignedIds->count(),
            'overdueCount' => PersonnelIntervention::query()->whereIn('personnel_id', (clone $personnelScope)->select('personnel.id'))
                ->where('status', InterventionStatus::Ongoing->value)->whereDate('next_follow_up_on', '<', today())->count(),
            'allPackages' => InterventionPackage::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
