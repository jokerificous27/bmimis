<?php

namespace App\Livewire\Assessments;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\BmiAssessment;
use App\Services\AssessmentService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('BMI Assessment')]
class Show extends Component
{
    public BmiAssessment $assessment;

    public string $certificationRemarks = '';

    public string $returnReason = '';

    public function mount(BmiAssessment $assessment): void
    {
        $this->authorize('view', $assessment);

        if (request()->routeIs('my.*')) {
            abort_unless($assessment->personnel->user_id === Auth::id(), 404);
        }

        $this->assessment = $assessment;
    }

    public function certify(AssessmentService $service): void
    {
        $this->authorize('certify', $this->assessment);
        $this->validate(['certificationRemarks' => ['nullable', 'string', 'max:500']]);

        $service->certify($this->assessment, Auth::user(), $this->certificationRemarks ?: null);

        Flux::modal('certify-assessment')->close();
        Flux::toast(variant: 'success', text: __('Assessment certified correct.'));
    }

    public function returnToDraft(AssessmentService $service): void
    {
        $this->authorize('certify', $this->assessment);
        $this->validate(['returnReason' => ['required', 'string', 'min:5', 'max:500']], [], ['returnReason' => 'reason']);

        $service->returnToDraft($this->assessment, $this->returnReason);

        Flux::modal('return-assessment')->close();
        Flux::toast(variant: 'warning', text: __('Assessment returned to the encoder as a draft.'));
    }

    public function render(): View
    {
        $this->assessment->load(['personnel.rank', 'personnel.unit', 'unit', 'rank', 'encoder', 'certifier', 'interventionPackage', 'supersededBy', 'creator', 'updater']);

        return view('livewire.assessments.show', [
            'history' => Auth::user()->hasPermission(Permission::ViewAuditTrail)
                ? ActivityLog::query()->where('subject_type', $this->assessment->getMorphClass())->where('subject_id', $this->assessment->id)->with('user:id,name')->latest('id')->get()
                : collect(),
            'isMine' => request()->routeIs('my.*') || $this->assessment->personnel->user_id === Auth::id(),
        ])->title(__('BMI Assessment — :date', ['date' => $this->assessment->assessed_on->format('M j, Y')]));
    }
}
