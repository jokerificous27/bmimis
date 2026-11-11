<?php

namespace App\Notifications;

use Carbon\CarbonInterface;

/**
 * Reminder to a Unit Representative (or Health Service staff) that personnel still lack a monthly weight.
 */
class ComplianceDeadlineApproaching extends BmiNotification
{
    public function __construct(public int $missing, public int $total, public CarbonInterface $deadline, public ?string $unitName = null) {}

    protected function title(): string
    {
        return __('Monthly compliance deadline approaching');
    }

    protected function message(): string
    {
        return __(':missing of :total personnel:unit have no weight recorded for :period. Deadline: :deadline.', [
            'missing' => $this->missing,
            'total' => $this->total,
            'unit' => $this->unitName ? ' in '.$this->unitName : '',
            'period' => $this->deadline->format('F Y'),
            'deadline' => $this->deadline->format('F j, Y'),
        ]);
    }

    protected function url(): ?string
    {
        return route('compliance.index');
    }

    protected function icon(): string
    {
        return 'exclamation-triangle';
    }

    protected function level(): string
    {
        return 'warning';
    }
}
