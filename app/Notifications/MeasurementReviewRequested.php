<?php

namespace App\Notifications;

use App\Models\MonthlyWeightMonitoring;

/**
 * Asks an administrator whether to update waist, hip and wrist after a large BMI change.
 */
class MeasurementReviewRequested extends BmiNotification
{
    public function __construct(public MonthlyWeightMonitoring $record) {}

    protected function title(): string
    {
        return __('BMI changed significantly: update measurements?');
    }

    protected function message(): string
    {
        return __(':name: BMI is now :bmi (:change since the last assessment). You may update the waist, hip and wrist measurements, or mark that no update is needed.', [
            'name' => $this->record->personnel->rank_name,
            'bmi' => number_format((float) $this->record->bmi, 1),
            'change' => ($this->record->bmi_change > 0 ? '+' : '').number_format((float) $this->record->bmi_change, 1),
        ]);
    }

    protected function url(): ?string
    {
        return route('monitoring.personnel', ['personnel' => $this->record->personnel_id, 'year' => $this->record->year]);
    }

    protected function icon(): string
    {
        return 'arrows-up-down';
    }

    protected function level(): string
    {
        return 'warning';
    }
}
