<?php

namespace App\Livewire\Compliance;

use App\Enums\Role;
use App\Livewire\Concerns\BuildsCharts;
use App\Livewire\Concerns\WithLookups;
use App\Models\ActivityLog;
use App\Models\MonthlyWeightMonitoring;
use App\Services\ComplianceService;
use App\Services\MonthlyMonitoringService;
use App\Services\PersonnelQuery;
use App\Support\Settings;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Compliance Monitoring')]
class Index extends Component
{
    use BuildsCharts, WithLookups;

    #[Url]
    public int $year = 0;

    #[Url]
    public int $month = 0;

    #[Url(except: '')]
    public string $unitId = '';

    public function mount(): void
    {
        $this->year = $this->year ?: (int) today()->format('Y');
        $this->month = $this->month ?: (int) today()->format('n');

        if (Auth::user()->hasUnitScope()) {
            $this->unitId = (string) Auth::user()->unit_id;
        }
    }

    public function selectUnit(string $unitId): void
    {
        $this->unitId = Auth::user()->hasUnitScope() ? (string) Auth::user()->unit_id : $unitId;
    }

    public function canSendReminders(): bool
    {
        return Auth::user()->hasRole(Role::SuperAdmin, Role::HealthServiceAdmin);
    }

    public function sendReminders(): void
    {
        abort_unless($this->canSendReminders(), 403);

        Artisan::call('bmi:send-reminders', ['--force' => true]);
        ActivityLog::record('notifications.reminders_sent', description: 'Monthly monitoring reminders sent manually');

        Flux::toast(variant: 'success', text: __('Reminders sent to personnel and unit representatives with pending monitoring.'));
    }

    public function render(ComplianceService $compliance, MonthlyMonitoringService $monitoring, Settings $settings): View
    {
        $user = Auth::user();
        $units = $compliance->byUnit($user, $this->year, $this->month);
        $scope = PersonnelQuery::for($user, ['unit_id' => $this->unitId]);
        $selectedUnit = $this->unitId !== '' ? $units->firstWhere(fn ($row) => $row['unit']->id === (int) $this->unitId) : null;

        $missing = $compliance->missingQuery($scope, $this->year, $this->month)
            ->with(['rank:id,abbreviation', 'unit:id,code'])
            ->orderBy('last_name')
            ->limit(100)
            ->get();

        $overall = $compliance->summary(PersonnelQuery::for($user), $this->year, $this->month);

        return view('livewire.compliance.index', [
            'units' => $units,
            'overall' => $overall,
            'selectedUnit' => $selectedUnit,
            'missing' => $missing,
            'missingTotal' => $compliance->missingQuery($scope, $this->year, $this->month)->count(),
            'trend' => $this->complianceChart($compliance->monthlyTrend($scope, $this->year), $settings->float('compliance_target_percent')),
            'target' => $settings->float('compliance_target_percent'),
            'deadline' => $monitoring->deadlineFor($this->year, $this->month),
            'periodStatus' => $monitoring->periodStatus($this->year, $this->month),
            'monthName' => MonthlyWeightMonitoring::MONTHS[$this->month],
        ]);
    }
}
