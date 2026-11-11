<?php

namespace App\Livewire\Concerns;

use App\Support\Settings;

/**
 * Validation rules for body measurements using the administrator-configured plausible ranges.
 * Units are fixed: centimetres for lengths, kilograms for weight.
 */
trait ValidatesMeasurements
{
    /**
     * @return list<string>
     */
    protected function measurementRule(string $measure, bool $required = true, int $decimals = 1): array
    {
        $settings = app(Settings::class);
        $unit = $measure === 'weight' ? 'kg' : 'cm';

        return [
            $required ? 'required' : 'nullable',
            'numeric',
            'decimal:0,'.$decimals,
            'min:'.$settings->get("{$measure}_min_{$unit}"),
            'max:'.$settings->get("{$measure}_max_{$unit}"),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function measurementMessages(): array
    {
        $settings = app(Settings::class);

        return [
            '*.min' => __('The value is below the plausible range. Check the unit of measure.'),
            '*.max' => __('The value is above the plausible range. Check the unit of measure.'),
            'height_cm.min' => __('Height must be at least :min cm. Enter height in centimetres (e.g. 169), not metres.', ['min' => $settings->get('height_min_cm')]),
            'height_cm.max' => __('Height must not exceed :max cm.', ['max' => $settings->get('height_max_cm')]),
            'weight_kg.min' => __('Weight must be at least :min kg.', ['min' => $settings->get('weight_min_kg')]),
            'weight_kg.max' => __('Weight must not exceed :max kg. Enter weight in kilograms, not pounds.', ['max' => $settings->get('weight_max_kg')]),
            '*.decimal' => __('Use at most :decimal decimal places.'),
        ];
    }
}
