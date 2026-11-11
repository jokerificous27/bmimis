<?php

namespace App\Console\Commands;

use App\Enums\InterventionStatus;
use App\Enums\Role;
use App\Models\Personnel;
use App\Models\PersonnelIntervention;
use App\Models\User;
use App\Notifications\ComplianceDeadlineApproaching;
use App\Notifications\FollowUpScheduled;
use App\Notifications\MonitoringDue;
use App\Services\ComplianceService;
use App\Services\MonthlyMonitoringService;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('bmi:send-reminders {--force : Send monthly monitoring reminders regardless of the configured reminder date}')]
#[Description('Send monthly weight monitoring reminders and intervention follow-up reminders')]
class SendMonitoringReminders extends Command
{
    public function handle(Settings $settings, MonthlyMonitoringService $monitoring, ComplianceService $compliance): int
    {
        $today = today();
        $deadline = $monitoring->deadlineFor($today->year, $today->month);
        $reminderDate = $deadline->startOfDay()->subDays($settings->int('reminder_days_before'));

        if ($this->option('force') || $today->isSameDay($reminderDate)) {
            $this->sendMonitoringReminders($today->year, $today->month, $deadline, $compliance);
        } else {
            $this->components->info("Monthly reminders are scheduled for {$reminderDate->format('F j, Y')}.");
        }

        $this->sendFollowUpReminders();

        return self::SUCCESS;
    }

    private function sendMonitoringReminders(int $year, int $month, CarbonInterface $deadline, ComplianceService $compliance): void
    {
        $missing = $compliance->missingQuery(Personnel::query(), $year, $month);

        $personnelNotified = 0;
        (clone $missing)->whereNotNull('user_id')->with('user')->chunkById(200, function ($people) use ($year, $month, $deadline, &$personnelNotified): void {
            foreach ($people as $person) {
                if ($person->user?->is_active) {
                    $person->user->notify(new MonitoringDue($year, $month, $deadline));
                    $personnelNotified++;
                }
            }
        });

        User::query()->where('role', Role::UnitRepresentative->value)->where('is_active', true)->whereNotNull('unit_id')->with('unit')->each(
            function (User $representative) use ($compliance, $year, $month, $deadline): void {
                $unitQuery = Personnel::query()->where('unit_id', $representative->unit_id);
                $summary = $compliance->summary($unitQuery, $year, $month);

                if ($summary['pending'] > 0) {
                    $representative->notify(new ComplianceDeadlineApproaching($summary['pending'], $summary['total'], $deadline, $representative->unit?->name));
                }
            },
        );

        $overall = $compliance->summary(Personnel::query(), $year, $month);

        if ($overall['pending'] > 0) {
            User::query()->whereIn('role', [Role::HealthServiceAdmin->value, Role::SuperAdmin->value])->where('is_active', true)
                ->each(fn (User $admin) => $admin->notify(new ComplianceDeadlineApproaching($overall['pending'], $overall['total'], $deadline)));
        }

        $this->components->info("Monthly monitoring reminders sent to {$personnelNotified} personnel.");
    }

    private function sendFollowUpReminders(): void
    {
        $count = 0;

        PersonnelIntervention::query()
            ->where('status', InterventionStatus::Ongoing->value)
            ->whereDate('next_follow_up_on', today()->addDay())
            ->whereHas('personnel', fn (Builder $query) => $query->whereNotNull('user_id'))
            ->with(['personnel.user', 'package'])
            ->each(function (PersonnelIntervention $intervention) use (&$count): void {
                $intervention->personnel->user?->notify(new FollowUpScheduled($intervention, isReminder: true));
                $count++;
            });

        $this->components->info("Follow-up reminders sent: {$count}.");
    }
}
