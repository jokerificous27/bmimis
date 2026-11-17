<?php

namespace App\Services\Bmi;

use App\Enums\FrameSize;
use App\Enums\PnpAcceptance;
use App\Enums\Sex;

/**
 * PNP Health Service rules that accept a BMI above the acceptable range for the age: a large body
 * frame (height-and-wrist ratio) or a waist circumference within the cut-off, both only when there
 * is no bulging abdomen. Pure arithmetic over the tables in config/pnp_bmi.php.
 */
class PnpAcceptanceRules
{
    /**
     * @param  array<string, mixed>  $rules  the "pnp_bmi" configuration
     */
    public function __construct(private array $rules) {}

    /**
     * Height (cm) ÷ wrist (cm), cut (not rounded) to one decimal as in the Health Service example.
     */
    public function heightWristRatio(float $heightCm, ?float $wristCm): ?float
    {
        if (! $wristCm || $wristCm <= 0) {
            return null;
        }

        return floor(round($heightCm / $wristCm * 10, 6)) / 10;
    }

    public function frameSize(?Sex $sex, ?float $heightWristRatio): ?FrameSize
    {
        $limits = $sex ? ($this->rules['frame'][$sex->value] ?? null) : null;

        if ($limits === null || $heightWristRatio === null) {
            return null;
        }

        return match (true) {
            $heightWristRatio < $limits['large_below'] => FrameSize::Large,
            $heightWristRatio > $limits['small_above'] => FrameSize::Small,
            default => FrameSize::Medium,
        };
    }

    /**
     * Highest BMI accepted for a large body frame at this age (the youngest group when the age is unknown).
     */
    public function largeFrameMaxBmi(?int $age): ?float
    {
        $row = $this->forAge($this->rules['large_frame_max_bmi'] ?? [], $age);

        return $row ? (float) $row['bmi'] : null;
    }

    /**
     * Waist circumference cut-off in centimetres (the youngest group when the age is unknown).
     */
    public function waistCutoffCm(?Sex $sex, ?int $age): ?float
    {
        $row = $sex ? $this->forAge($this->rules['waist']['cutoffs'] ?? [], $age) : null;

        return isset($row[$sex?->value]) ? (float) $row[$sex->value] : null;
    }

    /**
     * Decide whether a BMI above the acceptable range is still acceptable. The large body frame is
     * checked first, then the waist circumference. Nothing is accepted while the abdomen was not
     * observed, or is bulging.
     */
    public function acceptance(float $bmi, ?int $age, ?Sex $sex, ?FrameSize $frame, ?float $waistCm, ?bool $bulgingAbdomen): ?PnpAcceptance
    {
        if ($bulgingAbdomen !== false) {
            return null;
        }

        $largeFrameMax = $this->largeFrameMaxBmi($age);

        if ($frame === FrameSize::Large && $largeFrameMax !== null && $bmi <= $largeFrameMax) {
            return PnpAcceptance::LargeFrame;
        }

        $cutoff = $this->waistCutoffCm($sex, $age);
        $overweightBelow = (float) ($this->rules['waist']['overweight_below_bmi'] ?? 30);

        if ($waistCm && $cutoff !== null && $bmi < $overweightBelow && $waistCm <= $cutoff) {
            return PnpAcceptance::Waist;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows  age groups, youngest first; "max_age" null means "and above"
     * @return array<string, mixed>|null
     */
    private function forAge(array $rows, ?int $age): ?array
    {
        foreach ($rows as $row) {
            if ($age === null || $row['max_age'] === null || $age <= $row['max_age']) {
                return $row;
            }
        }

        return null;
    }
}
