<?php

namespace App\Notifications;

use App\Models\BmiAssessment;

class AssessmentAvailable extends BmiNotification
{
    public function __construct(public BmiAssessment $assessment, public bool $certified = false) {}

    protected function title(): string
    {
        return $this->certified ? __('BMI assessment certified') : __('New BMI assessment available');
    }

    protected function message(): string
    {
        return __('Your BMI assessment dated :date is :status. BMI: :bmi.', [
            'date' => $this->assessment->assessed_on->format('F j, Y'),
            'status' => $this->certified ? __('certified correct') : __('now available'),
            'bmi' => number_format($this->assessment->bmi, 1),
        ]);
    }

    protected function url(): ?string
    {
        return route('my.assessments.show', $this->assessment);
    }

    protected function icon(): string
    {
        return 'clipboard-document-check';
    }

    protected function level(): string
    {
        return 'success';
    }
}
