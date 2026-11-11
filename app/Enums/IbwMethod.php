<?php

namespace App\Enums;

enum IbwMethod: string
{
    case BmiRange = 'bmi_range';
    case Hamwi = 'hamwi';
    case Devine = 'devine';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::BmiRange => 'BMI range × height² (matches reference form)',
            self::Hamwi => 'Hamwi formula ± tolerance',
            self::Devine => 'Devine formula ± tolerance',
            self::Manual => 'Manually encoded by Health Service',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $method) => [$method->value => $method->label()])->all();
    }
}
