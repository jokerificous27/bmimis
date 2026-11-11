<?php

namespace App\Enums;

/**
 * Outcome of asking an administrator whether waist, hip and wrist should be measured again
 * after a monthly weigh-in showed a large BMI change.
 */
enum MeasurementReview: string
{
    case Pending = 'pending';
    case Updated = 'updated';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Measurements to review',
            self::Updated => 'Measurements updated',
            self::Declined => 'No update needed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Updated => 'green',
            self::Declined => 'zinc',
        };
    }
}
