<?php

namespace App\Enums;

enum ClassificationCategory: string
{
    case Below = 'below';
    case Normal = 'normal';
    case Above = 'above';

    public function label(): string
    {
        return match ($this) {
            self::Below => 'Below Normal',
            self::Normal => 'Normal',
            self::Above => 'Above Normal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Below => 'amber',
            self::Normal => 'green',
            self::Above => 'red',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $category) => [$category->value => $category->label()])->all();
    }
}
