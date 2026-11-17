<?php

namespace App\Enums;

/**
 * Body frame from the height-and-wrist ratio (PNP Health Service).
 */
enum FrameSize: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Small Frame',
            self::Medium => 'Medium Frame',
            self::Large => 'Large Frame',
        };
    }
}
