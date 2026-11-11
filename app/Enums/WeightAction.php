<?php

namespace App\Enums;

enum WeightAction: string
{
    case Lose = 'lose';
    case Maintain = 'maintain';
    case Gain = 'gain';

    public function label(): string
    {
        return match ($this) {
            self::Lose => 'Weight to lose',
            self::Maintain => 'Maintain',
            self::Gain => 'Weight to gain',
        };
    }

    /**
     * Human-readable result in the same wording as the official form, e.g. "Maintain" or "Lose 4.30 kg".
     */
    public function describe(?float $differenceKg): string
    {
        return match ($this) {
            self::Maintain => 'Maintain',
            self::Lose => 'Lose '.number_format(abs((float) $differenceKg), 2).' kg',
            self::Gain => 'Gain '.number_format(abs((float) $differenceKg), 2).' kg',
        };
    }
}
