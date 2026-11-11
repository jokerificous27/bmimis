<?php

use App\Enums\IbwMethod;
use App\Enums\Sex;
use App\Enums\WeightAction;
use App\Services\Bmi\BmiCalculator;

it('computes the BMI of the reference form', function () {
    expect((new BmiCalculator)->bmi(67.70, 169))->toBe(23.7);
});

it('rounds BMI to the configured precision', function () {
    expect((new BmiCalculator(precision: 2))->bmi(67.70, 169))->toBe(23.70)
        ->and((new BmiCalculator(precision: 1))->bmi(72.0, 170))->toBe(24.9)
        ->and((new BmiCalculator(precision: 2))->bmi(72.0, 170))->toBe(24.91);
});

it('rejects non-positive height or weight', function (float $weight, float $height) {
    (new BmiCalculator)->bmi($weight, $height);
})->throws(InvalidArgumentException::class)->with([
    'zero height' => [67.7, 0],
    'negative height' => [67.7, -169],
    'zero weight' => [0, 169],
    'negative weight' => [-5, 169],
]);

it('derives the reference IBW range from BMI 18.5–25 × height²', function () {
    $range = (new BmiCalculator)->idealBodyWeight(169, Sex::Male, IbwMethod::BmiRange);

    expect($range->minKg)->toBe(52.8)
        ->and($range->maxKg)->toBe(71.4)
        ->and($range->label())->toBe('52.8 kg – 71.4 kg');
});

it('uses configurable BMI bounds for the IBW range', function () {
    $range = (new BmiCalculator(ibwBmiLower: 20, ibwBmiUpper: 24))->idealBodyWeight(170, Sex::Female, IbwMethod::BmiRange);

    expect($range->minKg)->toBe(57.8)->and($range->maxKg)->toBe(69.4);
});

it('computes Hamwi IBW with tolerance and differs by sex', function () {
    $calculator = new BmiCalculator(ibwTolerancePercent: 10);
    $male = $calculator->idealBodyWeight(177.8, Sex::Male, IbwMethod::Hamwi);
    $female = $calculator->idealBodyWeight(177.8, Sex::Female, IbwMethod::Hamwi);

    // 5 ft 10 in: male 48 + 2.7 × 10 = 75 kg ± 10%, female 45.5 + 2.2 × 10 = 67.5 kg ± 10%.
    expect($male->minKg)->toBe(67.5)->and($male->maxKg)->toBe(82.5)
        ->and($female->minKg)->toBe(60.8)->and($female->maxKg)->toBe(74.3);
});

it('returns an unknown range for the manual IBW method', function () {
    expect((new BmiCalculator)->idealBodyWeight(169, Sex::Male, IbwMethod::Manual)->isKnown())->toBeFalse();
});

it('determines weight to lose, maintain or gain', function (float $weight, WeightAction $action, float $difference) {
    expect((new BmiCalculator)->weightAdjustment($weight, 52.8, 71.4))
        ->toBe(['action' => $action, 'difference' => $difference]);
})->with([
    'within range' => [67.70, WeightAction::Maintain, 0.0],
    'upper boundary' => [71.4, WeightAction::Maintain, 0.0],
    'above range' => [75.75, WeightAction::Lose, 4.35],
    'below range' => [50.0, WeightAction::Gain, 2.8],
]);

it('computes the waist–hip ratio only when both measurements exist', function () {
    $calculator = new BmiCalculator;

    expect($calculator->waistHipRatio(86, 93))->toBe(0.92)
        ->and($calculator->waistHipRatio(null, 93))->toBeNull();
});
