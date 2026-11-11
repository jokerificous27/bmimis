<?php

namespace App\Services\Bmi;

use App\Enums\IbwMethod;
use App\Enums\Sex;
use App\Enums\WeightAction;
use InvalidArgumentException;

/**
 * Pure BMI arithmetic. Contains no database access so results are reproducible and testable.
 */
class BmiCalculator
{
    private const CM_PER_INCH = 2.54;

    private const FIVE_FEET_IN_INCHES = 60;

    public function __construct(
        public int $precision = 1,
        public float $ibwBmiLower = 18.5,
        public float $ibwBmiUpper = 25.0,
        public float $ibwTolerancePercent = 10.0,
    ) {}

    /**
     * BMI = weight (kg) / height (m)², rounded to the configured precision.
     */
    public function bmi(float $weightKg, float $heightCm): float
    {
        $this->assertPositive($weightKg, 'Weight');
        $this->assertPositive($heightCm, 'Height');

        $heightM = $heightCm / 100;

        return round($weightKg / ($heightM * $heightM), $this->precision);
    }

    public function waistHipRatio(?float $waistCm, ?float $hipCm): ?float
    {
        if (! $waistCm || ! $hipCm) {
            return null;
        }

        return round($waistCm / $hipCm, 2);
    }

    /**
     * Ideal body weight range for the given height using the configured method.
     */
    public function idealBodyWeight(float $heightCm, ?Sex $sex, IbwMethod $method): IdealBodyWeightRange
    {
        $this->assertPositive($heightCm, 'Height');

        return match ($method) {
            IbwMethod::BmiRange => $this->bmiRangeIbw($heightCm),
            IbwMethod::Hamwi => $this->formulaIbw($heightCm, $method, $sex === Sex::Female ? [45.5, 2.2] : [48.0, 2.7]),
            IbwMethod::Devine => $this->formulaIbw($heightCm, $method, $sex === Sex::Female ? [45.5, 2.3] : [50.0, 2.3]),
            IbwMethod::Manual => new IdealBodyWeightRange(null, null, $method),
        };
    }

    /**
     * Whether the personnel should lose, maintain or gain weight to reach the target range,
     * and by how many kilograms.
     *
     * @return array{action: WeightAction|null, difference: float|null}
     */
    public function weightAdjustment(float $weightKg, ?float $targetMinKg, ?float $targetMaxKg): array
    {
        if ($targetMinKg === null || $targetMaxKg === null) {
            return ['action' => null, 'difference' => null];
        }

        if ($weightKg > $targetMaxKg) {
            return ['action' => WeightAction::Lose, 'difference' => round($weightKg - $targetMaxKg, 2)];
        }

        if ($weightKg < $targetMinKg) {
            return ['action' => WeightAction::Gain, 'difference' => round($targetMinKg - $weightKg, 2)];
        }

        return ['action' => WeightAction::Maintain, 'difference' => 0.0];
    }

    private function bmiRangeIbw(float $heightCm): IdealBodyWeightRange
    {
        $heightSquared = ($heightCm / 100) ** 2;

        return new IdealBodyWeightRange(
            round($this->ibwBmiLower * $heightSquared, 1),
            round($this->ibwBmiUpper * $heightSquared, 1),
            IbwMethod::BmiRange,
        );
    }

    /**
     * @param  array{0: float, 1: float}  $coefficients  base weight at five feet, kg per additional inch
     */
    private function formulaIbw(float $heightCm, IbwMethod $method, array $coefficients): IdealBodyWeightRange
    {
        [$base, $perInch] = $coefficients;

        $inchesOverFiveFeet = max(0, $heightCm / self::CM_PER_INCH - self::FIVE_FEET_IN_INCHES);
        $ideal = $base + $perInch * $inchesOverFiveFeet;
        $tolerance = $ideal * $this->ibwTolerancePercent / 100;

        return new IdealBodyWeightRange(round($ideal - $tolerance, 1), round($ideal + $tolerance, 1), $method);
    }

    private function assertPositive(float $value, string $label): void
    {
        if ($value <= 0 || ! is_finite($value)) {
            throw new InvalidArgumentException("{$label} must be a positive number.");
        }
    }
}
