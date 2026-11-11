<?php

use App\Enums\AssessmentStatus;
use App\Enums\Role;
use App\Livewire\Assessments\Form;
use App\Livewire\Assessments\Show;
use App\Models\ActivityLog;
use App\Models\BmiAssessment;
use App\Models\BmiClassification;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Unit;
use App\Notifications\AssessmentAvailable;
use App\Services\AssessmentService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->travelTo('2026-10-20 09:00');

    $this->unit = Unit::where('code', 'ITMS')->first();
    $this->encoder = userWithRole(Role::HealthServiceEncoder);
    $this->hsAdmin = userWithRole(Role::HealthServiceAdmin);
    $this->personnelUser = userWithRole(Role::Personnel);
    $this->personnel = personnelIn($this->unit, ['user_id' => $this->personnelUser->id, 'date_of_birth' => '1995-03-15']);
});

it('saves a submitted assessment with automatically computed results', function () {
    Notification::fake();

    Livewire::actingAs($this->encoder)
        ->test(Form::class)
        ->call('selectPersonnel', $this->personnel->id)
        ->set('assessed_on', '2026-10-06')
        ->set('height_cm', '169')
        ->set('weight_kg', '67.70')
        ->set('waist_cm', '86')
        ->set('hip_cm', '93')
        ->set('wrist_cm', '16')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $assessment = BmiAssessment::sole();

    expect($assessment->status)->toBe(AssessmentStatus::Submitted)
        ->and($assessment->bmi)->toBe(23.7)
        ->and($assessment->ibw_min_kg)->toBe(52.8)
        ->and($assessment->ibw_max_kg)->toBe(71.4)
        ->and($assessment->weightActionText())->toBe('Maintain')
        ->and($assessment->pnp_classification)->toBe('Normal')
        ->and($assessment->who_classification)->toBe('Normal')
        ->and($assessment->age_at_assessment)->toBe(31)
        ->and($assessment->unit_id)->toBe($this->unit->id)
        ->and($assessment->encoded_by)->toBe($this->encoder->id)
        ->and($assessment->created_by)->toBe($this->encoder->id)
        ->and(MonthlyWeightMonitoring::where('personnel_id', $this->personnel->id)->where('month', 10)->value('weight_kg'))->toEqual(67.70)
        ->and(ActivityLog::where('action', 'assessment.created')->where('subject_id', $assessment->id)->exists())->toBeTrue();

    Notification::assertSentTo($this->personnelUser, AssessmentAvailable::class);
});

it('converts a height entered in metres to centimetres', function () {
    Livewire::actingAs($this->encoder)
        ->test(Form::class)
        ->set('height_cm', '1.69')
        ->assertSet('height_cm', '169.0');
});

it('validates measurements with useful messages', function () {
    Livewire::actingAs($this->encoder)
        ->test(Form::class)
        ->call('selectPersonnel', $this->personnel->id)
        ->set('height_cm', '0')
        ->set('weight_kg', '-5')
        ->call('submit')
        ->assertHasErrors(['height_cm' => 'min', 'weight_kg' => 'min']);

    expect(BmiAssessment::count())->toBe(0);
});

it('requires confirmation before superseding a same-day assessment and preserves the original', function () {
    $this->actingAs($this->encoder);
    $original = app(AssessmentService::class)->create($this->personnel, ['assessed_on' => '2026-10-06', 'height_cm' => 169, 'weight_kg' => 70], submit: true);

    expect(fn () => app(AssessmentService::class)->create($this->personnel, ['assessed_on' => '2026-10-06', 'height_cm' => 169, 'weight_kg' => 67.7], submit: true))
        ->toThrow(ValidationException::class);

    $replacement = app(AssessmentService::class)->create($this->personnel, ['assessed_on' => '2026-10-06', 'height_cm' => 169, 'weight_kg' => 67.7], submit: true, confirmReplace: true);

    expect($original->fresh()->status)->toBe(AssessmentStatus::Superseded)
        ->and($original->fresh()->weight_kg)->toBe(70.0)
        ->and($original->fresh()->superseded_by_id)->toBe($replacement->id)
        ->and($this->personnel->latestAssessment()->first()->id)->toBe($replacement->id);
});

it('lets the Health Service administrator certify an assessment encoded by someone else', function () {
    Notification::fake();
    $this->actingAs($this->encoder);
    $assessment = app(AssessmentService::class)->create($this->personnel, ['assessed_on' => '2026-10-06', 'height_cm' => 169, 'weight_kg' => 67.7], submit: true);

    Livewire::actingAs($this->encoder)->test(Show::class, ['assessment' => $assessment])->call('certify')->assertForbidden();

    Livewire::actingAs($this->hsAdmin)->test(Show::class, ['assessment' => $assessment])
        ->set('certificationRemarks', 'Verified against logbook')
        ->call('certify')
        ->assertHasNoErrors();

    expect($assessment->fresh()->status)->toBe(AssessmentStatus::Certified)
        ->and($assessment->fresh()->certified_by)->toBe($this->hsAdmin->id)
        ->and(ActivityLog::where('action', 'assessment.certified')->exists())->toBeTrue();
});

it('blocks certifiers from certifying their own encoding', function () {
    $this->actingAs($this->hsAdmin);
    $assessment = app(AssessmentService::class)->create($this->personnel, ['assessed_on' => '2026-10-06', 'height_cm' => 169, 'weight_kg' => 67.7], submit: true);

    Livewire::test(Show::class, ['assessment' => $assessment])->call('certify')->assertForbidden();
});

it('corrects a certified assessment only with a reason and keeps an audit of the old values', function () {
    $this->actingAs($this->encoder);
    $assessment = app(AssessmentService::class)->create($this->personnel, ['assessed_on' => '2026-10-06', 'height_cm' => 169, 'weight_kg' => 76.0], submit: true);
    app(AssessmentService::class)->certify($assessment, $this->hsAdmin);

    $this->actingAs($this->encoder)->get(route('assessments.edit', $assessment))->assertForbidden();

    Livewire::actingAs($this->hsAdmin)
        ->test(Form::class, ['assessment' => $assessment->fresh()])
        ->set('weight_kg', '67.70')
        ->call('submit')
        ->assertHasErrors('correction_reason')
        ->set('correction_reason', 'Scale was not calibrated')
        ->call('submit')
        ->assertHasNoErrors();

    $assessment->refresh();
    $log = ActivityLog::where('action', 'assessment.updated')->where('subject_id', $assessment->id)->whereNotNull('remarks')->latest('id')->first();

    expect($assessment->weight_kg)->toBe(67.7)
        ->and($assessment->bmi)->toBe(23.7)
        ->and($assessment->status)->toBe(AssessmentStatus::Submitted)
        ->and($assessment->certified_by)->toBeNull()
        ->and($log->old_values['weight_kg'])->toEqual(76.0)
        ->and($log->remarks)->toContain('Scale was not calibrated');
});

it('suggests the intervention package configured for the PNP classification', function () {
    $this->actingAs($this->encoder);
    $assessment = app(AssessmentService::class)->create($this->personnel, ['assessed_on' => '2026-10-06', 'height_cm' => 169, 'weight_kg' => 80], submit: true);

    expect($assessment->pnp_classification)->toBe('Overweight')
        ->and($assessment->requires_intervention)->toBeTrue()
        ->and($assessment->interventionPackage->code)->toBe('B')
        ->and($assessment->weightActionText())->toBe('Lose 8.60 kg');
});

it('keeps historical classification snapshots when standards change later', function () {
    $this->actingAs($this->encoder);
    $assessment = app(AssessmentService::class)->create($this->personnel, ['assessed_on' => '2026-10-06', 'height_cm' => 169, 'weight_kg' => 80], submit: true);

    BmiClassification::query()->where('label', 'Overweight')->update(['label' => 'Renamed band']);

    expect($assessment->fresh()->pnp_classification)->toBe('Overweight');
});
