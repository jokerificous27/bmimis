<?php

use App\Enums\ClassificationCategory;
use App\Enums\FrameSize;
use App\Enums\PnpAcceptance;
use App\Enums\Role;
use App\Enums\Sex;
use App\Enums\StandardType;
use App\Models\BmiStandard;
use App\Models\Unit;
use App\Services\AssessmentService;
use App\Services\Bmi\BmiClassifier;
use App\Services\Bmi\BmiEvaluator;
use App\Services\Bmi\PnpAcceptanceRules;
use App\Services\MonthlyMonitoringService;

beforeEach(function () {
    seedReferenceData();
});

it('uses the official PNP Health Service chart in place of the provisional standard', function () {
    $standard = app(BmiClassifier::class)->standardFor(StandardType::Pnp, today());

    expect($standard->is_provisional)->toBeFalse()
        ->and($standard->name)->toContain('PNP HS Recommended')
        ->and(BmiStandard::query()->effectiveOn(StandardType::Pnp, today()->setDate(2019, 1, 1))->first()->is_provisional)->toBeTrue();
});

it('classifies the BMI according to age as on the PNP chart', function (float $bmi, ?int $age, string $label) {
    expect(app(BmiClassifier::class)->classify($bmi, StandardType::Pnp, today(), $age, Sex::Male)->label)->toBe($label);
})->with([
    [16.9, 30, 'Severely Underweight'],
    [17.0, 30, 'Underweight'],
    [18.4, 30, 'Underweight'],
    [18.5, 30, 'Normal'],
    [24.9, 25, 'Normal'],
    [25.0, 29, 'Overweight'],
    [25.0, 30, 'Acceptable BMI'],
    [25.1, 34, 'Overweight'],
    [25.5, 35, 'Acceptable BMI'],
    [25.6, 39, 'Overweight'],
    [26.0, 40, 'Acceptable BMI'],
    [26.1, 44, 'Overweight'],
    [26.5, 45, 'Acceptable BMI'],
    [26.6, 50, 'Overweight'],
    [27.0, 51, 'Acceptable BMI'],
    [27.1, 60, 'Overweight'],
    [29.9, 60, 'Overweight'],
    [30.0, 60, 'Obese Class 1'],
    [35.0, 41, 'Obese Class 2'],
    [40.0, 41, 'Obese Class 3'],
    'age unknown: strictest group' => [25.3, null, 'Overweight'],
]);

it('treats an acceptable BMI as within standard, without intervention', function () {
    $evaluation = app(BmiEvaluator::class)->evaluate(170, 75.1, today(), Sex::Male, 42);

    expect($evaluation->bmi)->toBe(26.0)
        ->and($evaluation->pnpLabel())->toBe('Acceptable BMI')
        ->and($evaluation->pnpCategory())->toBe(ClassificationCategory::Normal)
        ->and($evaluation->requiresIntervention())->toBeFalse();
});

it('interprets the height and wrist ratio as a body frame', function (Sex $sex, float $height, float $wrist, float $ratio, FrameSize $frame) {
    $rules = new PnpAcceptanceRules(config('pnp_bmi'));

    expect($rules->heightWristRatio($height, $wrist))->toBe($ratio)
        ->and($rules->frameSize($sex, $ratio))->toBe($frame);
})->with([
    'Health Service example' => [Sex::Female, 165, 18, 9.1, FrameSize::Large],
    'female medium, lower edge' => [Sex::Female, 163.4, 16.5, 9.9, FrameSize::Medium],
    'female medium, upper edge' => [Sex::Female, 163.5, 15, 10.9, FrameSize::Medium],
    'female small' => [Sex::Female, 165, 15, 11.0, FrameSize::Small],
    'male large' => [Sex::Male, 170, 18, 9.4, FrameSize::Large],
    'male medium' => [Sex::Male, 172.8, 18, 9.6, FrameSize::Medium],
    'male small' => [Sex::Male, 170, 16, 10.6, FrameSize::Small],
]);

it('reproduces the Health Service example: acceptable by large body frame', function () {
    $evaluation = app(BmiEvaluator::class)->evaluate(165, 75, today(), Sex::Female, 44, waistCm: 90, wristCm: 18, bulgingAbdomen: false);

    expect($evaluation->heightWristRatio)->toBe(9.1)
        ->and($evaluation->bmi)->toBe(27.5)
        ->and($evaluation->pnpClassification->label)->toBe('Overweight')
        ->and($evaluation->pnpAcceptance)->toBe(PnpAcceptance::LargeFrame)
        ->and($evaluation->pnpLabel())->toBe('Acceptable by Large Body Frame')
        ->and($evaluation->pnpCategory())->toBe(ClassificationCategory::Normal)
        ->and($evaluation->requiresIntervention())->toBeFalse()
        ->and($evaluation->suggestedInterventionPackageId())->toBeNull();
});

it('accepts a large body frame only up to the limit for the age and without a bulging abdomen', function (int $age, float $weight, ?bool $bulging, ?PnpAcceptance $expected) {
    // 165 cm, wrist 18 cm: large frame. Waist 95 cm is above every cut-off, so only the frame can accept.
    $evaluation = app(BmiEvaluator::class)->evaluate(165, $weight, today(), Sex::Female, $age, waistCm: 95, wristCm: 18, bulgingAbdomen: $bulging);

    expect($evaluation->pnpAcceptance)->toBe($expected);
})->with([
    '29 y/o at 27.3' => [29, 74.3, false, PnpAcceptance::LargeFrame],
    '29 y/o at 27.5, above 27.39' => [29, 75.0, false, null],
    '34 y/o at 27.5' => [34, 75.0, false, PnpAcceptance::LargeFrame],
    '39 y/o at 28.0' => [39, 76.2, false, PnpAcceptance::LargeFrame],
    '39 y/o at 28.1, above 28.02' => [39, 76.5, false, null],
    '44 y/o at 28.5' => [44, 77.6, false, PnpAcceptance::LargeFrame],
    '50 y/o at 29.0' => [50, 79.0, false, PnpAcceptance::LargeFrame],
    '50 y/o at 29.1, above 29.07' => [50, 79.2, false, null],
    '51 y/o at 29.6' => [51, 80.6, false, PnpAcceptance::LargeFrame],
    '51 y/o at 29.7, above 29.6' => [51, 80.9, false, null],
    'bulging abdomen' => [44, 75.0, true, null],
    'abdomen not observed' => [44, 75.0, null, null],
]);

it('accepts overweight personnel within the waist circumference cut-off and without a bulging abdomen', function (Sex $sex, int $age, float $waist, ?bool $bulging, string $label) {
    // 170 cm, 80 kg: BMI 27.7, overweight at every age. Wrist 17 cm: medium frame.
    $evaluation = app(BmiEvaluator::class)->evaluate(170, 80, today(), $sex, $age, waistCm: $waist, wristCm: 17, bulgingAbdomen: $bulging);

    expect($evaluation->bmi)->toBe(27.7)
        ->and($evaluation->pnpLabel())->toBe($label);
})->with([
    'male 44, 35 in.' => [Sex::Male, 44, 88.9, false, 'Acceptable by Waist Circumference'],
    'male 44, above 35 in.' => [Sex::Male, 44, 89.0, false, 'Overweight'],
    'male 45, 36 in.' => [Sex::Male, 45, 91.4, false, 'Acceptable by Waist Circumference'],
    'male 45, above 36 in.' => [Sex::Male, 45, 91.5, false, 'Overweight'],
    'female 44, 31 in.' => [Sex::Female, 44, 78.7, false, 'Acceptable by Waist Circumference'],
    'female 44, above 31 in.' => [Sex::Female, 44, 78.8, false, 'Overweight'],
    'female 45, 32 in.' => [Sex::Female, 45, 81.3, false, 'Acceptable by Waist Circumference'],
    'female 45, above 32 in.' => [Sex::Female, 45, 81.4, false, 'Overweight'],
    'bulging abdomen' => [Sex::Male, 30, 85.0, true, 'Overweight'],
    'abdomen not observed' => [Sex::Male, 30, 85.0, null, 'Overweight'],
]);

it('never applies the waist circumference rule to obese personnel', function () {
    $evaluation = app(BmiEvaluator::class)->evaluate(170, 90, today(), Sex::Male, 30, waistCm: 85, wristCm: 17, bulgingAbdomen: false);

    expect($evaluation->bmi)->toBe(31.1)
        ->and($evaluation->pnpAcceptance)->toBeNull()
        ->and($evaluation->pnpLabel())->toBe('Obese Class 1')
        ->and($evaluation->requiresIntervention())->toBeTrue();
});

it('stores the frame, the observation and the acceptance on the assessment and the monthly record', function () {
    $this->actingAs(userWithRole(Role::HealthServiceAdmin));
    $personnel = personnelIn(Unit::where('code', 'ITMS')->first(), ['sex' => 'female', 'date_of_birth' => today()->subYears(44)->subMonth()->format('Y-m-d')]);

    $assessment = app(AssessmentService::class)->create($personnel, [
        'assessed_on' => today()->format('Y-m-d'), 'height_cm' => 165, 'weight_kg' => 75, 'waist_cm' => 90, 'hip_cm' => 100, 'wrist_cm' => 18, 'bulging_abdomen' => false,
    ], submit: true, recordMonthlyWeight: true)->fresh();

    $monthly = $personnel->monthlyWeights()->sole();

    expect($assessment->height_wrist_ratio)->toBe(9.1)
        ->and($assessment->frame_size)->toBe(FrameSize::Large)
        ->and($assessment->bulging_abdomen)->toBeFalse()
        ->and($assessment->pnp_acceptance)->toBe(PnpAcceptance::LargeFrame)
        ->and($assessment->pnp_classification)->toBe('Acceptable by Large Body Frame')
        ->and($assessment->pnp_category)->toBe(ClassificationCategory::Normal)
        ->and($assessment->requires_intervention)->toBeFalse()
        ->and($monthly->pnp_classification)->toBe('Acceptable by Large Body Frame')
        ->and($monthly->pnp_category)->toBe(ClassificationCategory::Normal);
});

it('uses the measurements of the assessment in force for a monthly weight typed later', function () {
    $this->actingAs(userWithRole(Role::HealthServiceAdmin));
    $personnel = personnelIn(Unit::where('code', 'ITMS')->first(), ['sex' => 'male', 'date_of_birth' => today()->subYears(30)->subMonth()->format('Y-m-d')]);
    $lastMonth = today()->startOfMonth()->subDay();

    app(AssessmentService::class)->create($personnel, [
        'assessed_on' => $lastMonth->format('Y-m-d'), 'height_cm' => 170, 'weight_kg' => 80, 'waist_cm' => 86, 'wrist_cm' => 17, 'bulging_abdomen' => false,
    ], submit: true);

    $record = app(MonthlyMonitoringService::class)->record($personnel, (int) today()->format('Y'), (int) today()->format('n'), 80.5, today());

    expect($record->pnp_classification)->toBe('Acceptable by Waist Circumference');
});
