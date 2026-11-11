<?php

namespace App\Livewire\Assessments;

use App\Enums\AssessmentStatus;
use App\Enums\ClassificationCategory;
use App\Enums\Permission;
use App\Livewire\Concerns\WithLookups;
use App\Models\BmiAssessment;
use App\Models\Personnel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('BMI Assessments')]
class Index extends Component
{
    use WithLookups, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $unitId = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Locked]
    public bool $mine = false;

    public function mount(): void
    {
        $this->mine = request()->routeIs('my.*');

        if (! $this->mine) {
            $this->authorize('viewAny', BmiAssessment::class);
        }
    }

    public function updating(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $user = Auth::user();
        $canSeeDrafts = ! $this->mine && $user->hasPermission(Permission::EncodeAssessments);

        $assessments = BmiAssessment::query()
            ->whereHas('personnel', function (Builder $query) use ($user): void {
                $this->mine ? $query->where('user_id', $user->id) : $query->visibleTo($user);
                $query->search($this->search);
            })
            ->when(! $canSeeDrafts, fn (Builder $query) => $query->where('status', '!=', AssessmentStatus::Draft->value))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->unitId !== '' && ! $this->mine, fn (Builder $query) => $query->where('unit_id', $this->unitId))
            ->when($this->category !== '', fn (Builder $query) => $query->where('pnp_category', $this->category))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('assessed_on', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('assessed_on', '<=', $this->to))
            ->with(['personnel.rank:id,abbreviation', 'unit:id,code', 'encoder:id,name'])
            ->orderByDesc('assessed_on')->orderByDesc('id')
            ->paginate(20);

        $pendingCertification = $this->mine ? 0 : BmiAssessment::query()
            ->where('status', AssessmentStatus::Submitted->value)
            ->whereHas('personnel', fn (Builder $query) => $query->visibleTo($user))
            ->count();

        return view('livewire.assessments.index', [
            'assessments' => $assessments,
            'statuses' => collect(AssessmentStatus::cases())->reject(fn ($s) => $s === AssessmentStatus::Draft && ! $canSeeDrafts),
            'categories' => ClassificationCategory::options(),
            'pendingCertification' => $pendingCertification,
            'canCertify' => $user->hasPermission(Permission::CertifyAssessments),
        ])->title($this->mine ? __('My Assessment History') : __('BMI Assessments'));
    }

    /**
     * Route to an assessment from this list (personnel accounts use their own route).
     */
    public function showUrl(BmiAssessment $assessment): string
    {
        return $this->mine ? route('my.assessments.show', $assessment) : route('assessments.show', $assessment);
    }

    public function personnelUrl(Personnel $personnel): string
    {
        return $this->mine ? route('my.profile') : route('personnel.show', $personnel);
    }
}
