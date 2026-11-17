<?php

namespace App\Enums;

/**
 * Why personnel with a BMI above the acceptable range for their age are still classified as acceptable.
 */
enum PnpAcceptance: string
{
    case LargeFrame = 'large_frame';
    case Waist = 'waist';

    /**
     * The classification written on the record.
     */
    public function label(): string
    {
        return match ($this) {
            self::LargeFrame => 'Acceptable by Large Body Frame',
            self::Waist => 'Acceptable by Waist Circumference',
        };
    }
}
