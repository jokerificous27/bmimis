<?php

namespace App\Enums;

enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Certified = 'certified';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted for certification',
            self::Certified => 'Certified',
            self::Superseded => 'Superseded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Submitted => 'amber',
            self::Certified => 'green',
            self::Superseded => 'red',
        };
    }

    /**
     * Statuses that count as an official, non-draft result.
     *
     * @return list<string>
     */
    public static function officialValues(): array
    {
        return [self::Submitted->value, self::Certified->value];
    }
}
