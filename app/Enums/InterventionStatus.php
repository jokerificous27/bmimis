<?php

namespace App\Enums;

enum InterventionStatus: string
{
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Discontinued = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::Ongoing => 'Ongoing',
            self::Completed => 'Completed',
            self::Discontinued => 'Discontinued',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ongoing => 'blue',
            self::Completed => 'green',
            self::Discontinued => 'zinc',
        };
    }
}
