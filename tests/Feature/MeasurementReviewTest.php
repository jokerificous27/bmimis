<?php

use App\Enums\MeasurementReview as ReviewStatus;
use App\Enums\Role;
use App\Livewire\Monitoring\MeasurementReview;
use App\Livewire\Monitoring\Station;
use App\Models\BmiAssessment;
use App\Models\Unit;
use App\Notifications\MeasurementReviewRequested;
use App\Services\AssessmentService;
use App\Services\MonthlyMonitoringService;
use App\Support\Settings;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->travelTo('2026-10-20 09:00');

    $this->unit = Unit::where('code', 'ITMS')->first();
    $this->admin = userWithRole(Role::HealthServiceAdmin);
    $this->encoder = userWithRole(Role::HealthServiceEncoder);
    $this->actingAs($this->encoder);

    // BMI 24.0 at 169 cm; the default threshold of 2.0 BMI points is reached from 74.3 kg.
    $this->personnel = personnelIn($this->unit);
    $this->baseline = app(AssessmentService::class)->create($this->personnel, [
        'assessed_on' => '2026-01-10', 'height_cm' => 169, 'weight_kg' => 68.5, 'waist_cm' => 82, 'hip_cm' => 96, 'wrist_cm' => 16.5,
    ], submit: true);

    $this->monitoring = app(MonthlyMonitoringService::class);
});

it('asks the staff who encode assessments to review the measurements when the BMI changed a lot', function () {
    Notification::fake();

    $record = $this->monitoring->record($this->personnel, 2026, 10, 75.0);

    expect($record->measurement_review)->toBe(ReviewStatus::Pending)
        ->and($record->bmi_change)->toBe(2.3)
        ->and($record->baseline_assessment_id)->toBe($this->baseline->id);

    Notification::assertSentTo($this->admin, MeasurementReviewRequested::class);
    Notification::assertSentTo($this->encoder, MeasurementReviewRequested::class);
    Notification::assertNotSentTo(userWithRole(Role::UnitRepresentative, $this->unit), MeasurementReviewRequested::class);
});

it('does not ask for a small BMI change', function () {
    Notification::fake();

    $record = $this->monitoring->record($this->personnel, 2026, 10, 70.0);

    expect($record->measurement_review)->toBeNull();
    Notification::assertNothingSentTo($this->admin);
});

it('uses the configured BMI change threshold', function () {
    app(Settings::class)->set(['significant_bmi_change' => 3.0]);

    expect($this->monitoring->record($this->personnel, 2026, 10, 75.0)->measurement_review)->toBeNull();
});

it('does not ask when an assessment was made on or after the weigh-in', function () {
    app(AssessmentService::class)->create($this->personnel, ['assessed_on' => '2026-10-15', 'height_cm' => 169, 'weight_kg' => 75], submit: true);

    $record = $this->monitoring->record($this->personnel, 2026, 10, 75.0, now()->subDays(6));

    expect($record->measurement_review)->toBeNull();
});

it('withdraws the question when a correction removes the BMI change', function () {
    $record = $this->monitoring->record($this->personnel, 2026, 10, 75.0);

    $this->monitoring->correct($record, 69.0, 'Scale was misread');

    expect($record->fresh()->measurement_review)->toBeNull();
});

it('lets an administrator update waist, hip and wrist as a new assessment', function () {
    $record = $this->monitoring->record($this->personnel, 2026, 10, 75.0, today());

    Livewire::actingAs($this->admin)->test(MeasurementReview::class, ['recordId' => $record->id])
        ->assertSee('Do you want to update the waist, hip and wrist too?')
        ->call('startUpdate')
        ->assertSet('waist_cm', '82.0')
        ->assertSet('wrist_cm', '16.5')
        ->set('waist_cm', '90')
        ->set('hip_cm', '100.5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('outcome', 'updated');

    $assessment = BmiAssessment::query()->whereKeyNot($this->baseline->id)->sole();

    expect($assessment->waist_cm)->toBe(90.0)
        ->and($assessment->hip_cm)->toBe(100.5)
        ->and($assessment->wrist_cm)->toBe(16.5)
        ->and($assessment->weight_kg)->toBe(75.0)
        ->and($assessment->height_cm)->toBe(169.0)
        ->and($assessment->assessed_on->format('Y-m-d'))->toBe('2026-10-20')
        ->and($this->baseline->fresh()->waist_cm)->toBe(82.0)
        ->and($record->fresh()->measurement_review)->toBe(ReviewStatus::Updated)
        ->and($record->fresh()->measurement_reviewed_by)->toBe($this->admin->id);
});

it('lets an administrator decide that no update is needed', function () {
    $record = $this->monitoring->record($this->personnel, 2026, 10, 75.0);

    Livewire::actingAs($this->admin)->test(MeasurementReview::class, ['recordId' => $record->id])
        ->call('decline')
        ->assertSet('outcome', 'declined');

    expect($record->fresh()->measurement_review)->toBe(ReviewStatus::Declined)
        ->and(BmiAssessment::count())->toBe(1);

    // A declined review is not reopened by a later correction.
    $this->monitoring->correct($record->fresh(), 76.0, 'Re-weighed');

    expect($record->fresh()->measurement_review)->toBe(ReviewStatus::Declined);
});

it('requires at least one measurement when updating', function () {
    $record = $this->monitoring->record($this->personnel, 2026, 10, 75.0);

    Livewire::actingAs($this->admin)->test(MeasurementReview::class, ['recordId' => $record->id])
        ->call('startUpdate')
        ->set('waist_cm', '')->set('hip_cm', '')->set('wrist_cm', '')
        ->call('save')
        ->assertHasErrors('waist_cm');

    expect($record->fresh()->measurement_review)->toBe(ReviewStatus::Pending);
});

it('lets an encoder update waist, hip and wrist', function () {
    $record = $this->monitoring->record($this->personnel, 2026, 10, 75.0, today());

    Livewire::test(MeasurementReview::class, ['recordId' => $record->id])
        ->call('startUpdate')
        ->set('waist_cm', '88')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('outcome', 'updated');

    expect(BmiAssessment::query()->whereKeyNot($this->baseline->id)->sole()->waist_cm)->toBe(88.0)
        ->and($record->fresh()->measurement_reviewed_by)->toBe($this->encoder->id);
});

it('only informs users who do not encode assessments', function () {
    $record = $this->monitoring->record($this->personnel, 2026, 10, 75.0);

    Livewire::actingAs(userWithRole(Role::UnitRepresentative, $this->unit))->test(MeasurementReview::class, ['recordId' => $record->id])
        ->assertSee('The Health Service has been asked')
        ->assertDontSee('Yes, update measurements')
        ->call('decline')
        ->assertForbidden();

    expect($record->fresh()->measurement_review)->toBe(ReviewStatus::Pending);
});

it('shows the question at the Weigh-in Station right after a weigh-in with a large BMI change', function () {
    app(Settings::class)->set(['weight_reader' => 'off']);

    Livewire::actingAs($this->admin)->test(Station::class)
        ->call('identify', $this->personnel->qrPayload())
        ->set('weight', '75')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('lastSaved.review_record_id', $this->personnel->monthlyWeights()->value('id'))
        ->assertSee('Do you want to update the waist, hip and wrist too?');
});
