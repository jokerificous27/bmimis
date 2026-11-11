<?php

namespace App\Enums;

enum PersonnelStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Retired = 'retired';
    case Separated = 'separated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Retired => 'Retired',
            self::Separated => 'Separated',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Inactive => 'zinc',
            self::Retired => 'blue',
            self::Separated => 'red',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
