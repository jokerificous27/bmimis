<?php

namespace App\Notifications;

use App\Models\PersonnelIntervention;

class InterventionAssigned extends BmiNotification
{
    public function __construct(public PersonnelIntervention $intervention) {}

    protected function title(): string
    {
        return __('Intervention package assigned');
    }

    protected function message(): string
    {
        return __(':package has been assigned to you by the Health Service.:followup', [
            'package' => $this->intervention->package->displayName().' – '.$this->intervention->package->name,
            'followup' => $this->intervention->next_follow_up_on ? ' '.__('First follow-up: :date.', ['date' => $this->intervention->next_follow_up_on->format('F j, Y')]) : '',
        ]);
    }

    protected function url(): ?string
    {
        return route('my.profile');
    }

    protected function icon(): string
    {
        return 'heart';
    }
}
