<?php

namespace App\Enums;

enum StandardType: string
{
    case Who = 'who';
    case Pnp = 'pnp';

    public function label(): string
    {
        return match ($this) {
            self::Who => 'WHO Standard',
            self::Pnp => 'PNP Acceptable BMI',
        };
    }
}
