<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only audit trail. There are no edit or delete actions, and the model itself rejects updates and deletes.
 */
#[Title('Audit Trail')]
class AuditTrail extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $action = '';

    #[Url(except: '')]
    public string $userId = '';

    #[Url(as: 'personnel', except: '')]
    public string $personnelId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $search = '';

    public function updating(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $logs = ActivityLog::query()
            ->when($this->action !== '', fn (Builder $query) => $query->where('action', 'like', $this->action.'%'))
            ->when($this->userId !== '', fn (Builder $query) => $query->where('user_id', $this->userId))
            ->when($this->personnelId !== '', fn (Builder $query) => $query->where('personnel_id', $this->personnelId))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->to))
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn ($q) => $q->where('description', 'like', "%{$this->search}%")->orWhere('remarks', 'like', "%{$this->search}%")))
            ->with(['user:id,name', 'personnel:id,last_name,first_name,middle_name,employee_number'])
            ->latest('id')
            ->paginate(25);

        return view('livewire.admin.audit-trail', [
            'logs' => $logs,
            'actions' => [
                'auth' => __('Authentication'),
                'assessment' => __('BMI assessments'),
                'monthly_weight' => __('Monthly weights'),
                'personnel' => __('Personnel'),
                'intervention' => __('Interventions'),
                'report' => __('Reports & exports'),
                'user' => __('User accounts'),
                'permissions' => __('Permissions'),
                'setting' => __('System settings'),
                'bmi_standard' => __('BMI standards'),
                'bmi_classification' => __('BMI classifications'),
                'import' => __('Imports'),
            ],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'filteredPersonnel' => $this->personnelId !== '' ? Personnel::withTrashed()->find($this->personnelId) : null,
        ]);
    }
}
