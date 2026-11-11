<?php

namespace App\Notifications;

use App\Models\PersonnelIntervention;

class FollowUpScheduled extends BmiNotification
{
    public function __construct(public PersonnelIntervention $intervention, public bool $isReminder = false) {}

    protected function title(): string
    {
        return $this->isReminder ? __('Follow-up assessment reminder') : __('Follow-up assessment scheduled');
    }

    protected function message(): string
    {
        return __('Your Health Service follow-up for :package is scheduled on :date.', [
            'package' => $this->intervention->package->displayName(),
            'date' => $this->intervention->next_follow_up_on?->format('F j, Y') ?? '—',
        ]);
    }

    protected function url(): ?string
    {
        return route('my.profile');
    }

    protected function icon(): string
    {
        return 'calendar';
    }
}
