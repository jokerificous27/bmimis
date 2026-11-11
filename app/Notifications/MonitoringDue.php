<?php

namespace App\Notifications;

use Carbon\CarbonInterface;

/**
 * Reminder to a personnel member that this month's weigh-in is due.
 */
class MonitoringDue extends BmiNotification
{
    public function __construct(public int $year, public int $month, public CarbonInterface $deadline) {}

    protected function title(): string
    {
        return __('Monthly BMI monitoring is due');
    }

    protected function message(): string
    {
        return __('Please report to your Health Service representative for your :period weigh-in on or before :deadline.', [
            'period' => $this->deadline->format('F Y'),
            'deadline' => $this->deadline->format('F j, Y'),
        ]);
    }

    protected function url(): ?string
    {
        return route('my.monitoring', ['year' => $this->year]);
    }

    protected function icon(): string
    {
        return 'calendar-days';
    }

    protected function level(): string
    {
        return 'warning';
    }
}
