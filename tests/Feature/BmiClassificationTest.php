<?php

use App\Enums\ClassificationCategory;
use App\Enums\Sex;
use App\Enums\StandardType;
use App\Models\BmiStandard;
use App\Services\Bmi\BmiClassifier;
use App\Services\Bmi\BmiEvaluator;
use Carbon\CarbonImmutable;

beforeEach(fn () => seedReferenceData());

it('classifies BMI against WHO thresholds with inclusive lower and exclusive upper bounds', function (float $bmi, string $label) {
    $classification = app(BmiClassifier::class)->classify($bmi, StandardType::Who, today());

    expect($classification->label)->toBe($label);
})->with([
    [18.4, 'Underweight'],
    [18.5, 'Normal'],
    [24.9, 'Normal'],
    [25.0, 'Pre-obese'],
    [30.0, 'Obese Class I'],
    [40.0, 'Obese Class III'],
]);

it('classifies PNP and WHO separately', function () {
    $evaluation = app(BmiEvaluator::class)->evaluate(169, 67.70, today(), Sex::Male, 31);

    expect($evaluation->bmi)->toBe(23.7)
        ->and($evaluation->pnpClassification->standard->type)->toBe(StandardType::Pnp)
        ->and($evaluation->whoClassification->standard->type)->toBe(StandardType::Who)
        ->and($evaluation->idealBodyWeight->label())->toBe('52.8 kg – 71.4 kg')
        ->and($evaluation->weightActionText())->toBe('Maintain');
});

it('uses the standard version in force on the assessment date', function () {
    $newStandard = BmiStandard::create([
        'type' => StandardType::Pnp, 'name' => 'Official PNP v2', 'effective_from' => '2026-06-01', 'is_active' => true,
    ]);
    $newStandard->classifications()->create(['label' => 'Acceptable', 'category' => ClassificationCategory::Normal, 'min_bmi' => 18.5, 'max_bmi' => 27, 'color' => 'green']);

    $classifier = app(BmiClassifier::class);

    expect($classifier->classify(26.0, StandardType::Pnp, CarbonImmutable::parse('2026-05-31'))->label)->toBe('Overweight')
        ->and($classifier->classify(26.0, StandardType::Pnp, CarbonImmutable::parse('2026-06-01'))->label)->toBe('Acceptable');
});

it('prefers age-specific bands over general bands', function () {
    $standard = BmiStandard::query()->where('type', StandardType::Pnp->value)->first();
    $standard->classifications()->create(['label' => 'Acceptable (40+)', 'category' => ClassificationCategory::Normal, 'min_bmi' => 25, 'max_bmi' => 27, 'min_age' => 40, 'color' => 'green']);

    $classifier = app(BmiClassifier::class);

    expect($classifier->classify(26.0, StandardType::Pnp, today(), age: 45)->label)->toBe('Acceptable (40+)')
        ->and($classifier->classify(26.0, StandardType::Pnp, today(), age: 30)->label)->toBe('Overweight');
});

it('returns no classification when no standard is configured', function () {
    BmiStandard::query()->where('type', StandardType::Pnp->value)->update(['is_active' => false]);

    expect(app(BmiClassifier::class)->classify(22, StandardType::Pnp, today()))->toBeNull();
});
