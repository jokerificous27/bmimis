<?php

namespace App\Notifications;

use App\Models\MonthlyWeightMonitoring;

class MonthlyWeightRecorded extends BmiNotification
{
    public function __construct(public MonthlyWeightMonitoring $record) {}

    protected function title(): string
    {
        return __('Monthly weight recorded');
    }

    protected function message(): string
    {
        return __('Your weight for :month :year was recorded: :weight kg.', [
            'month' => $this->record->monthName(),
            'year' => $this->record->year,
            'weight' => number_format($this->record->weight_kg, 2),
        ]);
    }

    protected function url(): ?string
    {
        return route('my.monitoring', ['year' => $this->record->year]);
    }

    protected function icon(): string
    {
        return 'scale';
    }
}
