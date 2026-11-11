<?php

use App\Enums\Role;
use App\Livewire\Monitoring\Index;
use App\Livewire\Monitoring\PersonnelYear;
use App\Models\ActivityLog;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use App\Models\Unit;
use App\Services\AssessmentService;
use App\Services\ComplianceService;
use App\Services\MonthlyMonitoringService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->travelTo('2026-10-20 09:00');

    $this->unit = Unit::where('code', 'ITMS')->first();
    $this->encoder = userWithRole(Role::HealthServiceEncoder);
    $this->actingAs($this->encoder);

    $this->personnel = personnelIn($this->unit);
    app(AssessmentService::class)->create($this->personnel, ['assessed_on' => '2026-01-10', 'height_cm' => 169, 'weight_kg' => 68.5], submit: true);

    $this->monitoring = app(MonthlyMonitoringService::class);
});

it('computes BMI using the height from the applicable assessment', function () {
    $record = $this->monitoring->record($this->personnel, 2026, 10, 67.70);

    expect($record->height_cm)->toBe(169.0)
        ->and($record->bmi)->toBe(23.7)
        ->and($record->pnp_classification)->toBe('Normal')
        ->and($record->who_classification)->toBe('Normal')
        ->and($record->height_source_assessment_id)->not->toBeNull();
});

it('keeps the stored height when a later assessment changes the height', function () {
    $record = $this->monitoring->record($this->personnel, 2026, 3, 68.0);

    app(AssessmentService::class)->create($this->personnel, ['assessed_on' => '2026-06-01', 'height_cm' => 171, 'weight_kg' => 68.0], submit: true);

    expect($record->fresh()->height_cm)->toBe(169.0)
        ->and($this->monitoring->record($this->personnel, 2026, 7, 68.0)->height_cm)->toBe(171.0);
});

it('allows only one record per personnel per month', function () {
    $this->monitoring->record($this->personnel, 2026, 9, 68.95);

    expect(fn () => $this->monitoring->record($this->personnel, 2026, 9, 69.10))->toThrow(ValidationException::class);
    expect(MonthlyWeightMonitoring::where('personnel_id', $this->personnel->id)->where('year', 2026)->where('month', 9)->count())->toBe(1);
});

it('enforces the monthly uniqueness at the database level', function () {
    MonthlyWeightMonitoring::factory()->for($this->personnel)->create(['year' => 2025, 'month' => 5]);

    expect(fn () => MonthlyWeightMonitoring::factory()->for($this->personnel)->create(['year' => 2025, 'month' => 5]))->toThrow(QueryException::class);
});

it('rejects weights for future months', function () {
    expect(fn () => $this->monitoring->record($this->personnel, 2026, 11, 68.0))->toThrow(ValidationException::class);
});

it('keeps history across years and computes month-to-month differences', function () {
    $this->monitoring->record($this->personnel, 2025, 12, 69.0);
    $this->monitoring->record($this->personnel, 2026, 1, 68.5);
    $this->monitoring->record($this->personnel, 2026, 2, 68.3);
    $this->monitoring->record($this->personnel, 2026, 3, 68.3);
    $this->monitoring->record($this->personnel, 2026, 5, 68.9);

    $sheet = $this->monitoring->yearSheet($this->personnel, 2026)->keyBy('month');

    expect($sheet[1]['difference'])->toBe(-0.5)->and($sheet[1]['trend'])->toBe('down')
        ->and($sheet[2]['difference'])->toBe(-0.2)
        ->and($sheet[3]['trend'])->toBe('same')
        ->and($sheet[4]['status'])->toBe('missing')
        ->and($sheet[5]['difference'])->toBe(0.6)->and($sheet[5]['trend'])->toBe('up')
        ->and($sheet[10]['status'])->toBe('pending')
        ->and($sheet[11]['status'])->toBe('upcoming')
        ->and($this->monitoring->yearSheet($this->personnel, 2025)->firstWhere('month', 12)['record']->weight_kg)->toBe(69.0);
});

it('requires a reason to correct an entry and records the previous value', function () {
    $record = $this->monitoring->record($this->personnel, 2026, 8, 66.55);

    expect(fn () => $this->monitoring->correct($record, 67.55, ''))->toThrow(ValidationException::class);

    $this->monitoring->correct($record, 67.55, 'Encoding error: wrong digit');

    $log = ActivityLog::where('action', 'monthly_weight.updated')->where('subject_id', $record->id)->latest('id')->first();

    expect($record->fresh()->weight_kg)->toBe(67.55)
        ->and($log->old_values['weight_kg'])->toEqual(66.55)
        ->and($log->new_values['weight_kg'])->toEqual(67.55)
        ->and($log->remarks)->toContain('wrong digit')
        ->and($log->user_id)->toBe($this->encoder->id);
});

it('prevents encoders without correction rights from editing entries', function () {
    $record = $this->monitoring->record($this->personnel, 2026, 8, 66.55);

    Livewire::test(PersonnelYear::class, ['personnel' => $this->personnel])
        ->call('openCorrection', $record->id)
        ->assertForbidden();
});

it('records weights in batch from the monthly encoding sheet', function () {
    $second = personnelIn($this->unit);

    Livewire::test(Index::class)
        ->set('year', 2026)->set('month', 10)
        ->set("weights.{$this->personnel->id}", '67.70')
        ->set("weights.{$second->id}", '81.25')
        ->set('measuredOn', '2026-10-06')
        ->call('saveBatch')
        ->assertHasNoErrors();

    expect(MonthlyWeightMonitoring::where('year', 2026)->where('month', 10)->count())->toBe(2)
        ->and(MonthlyWeightMonitoring::where('personnel_id', $this->personnel->id)->where('month', 10)->value('bmi'))->toEqual(23.7);
});

it('rejects implausible weights in the encoding sheet', function () {
    Livewire::test(Index::class)
        ->set("weights.{$this->personnel->id}", '150000')
        ->call('saveBatch')
        ->assertHasErrors("weights.{$this->personnel->id}");

    expect(MonthlyWeightMonitoring::count())->toBe(0);
});

it('calculates monthly compliance percentages', function () {
    $others = Personnel::factory()->count(3)->create(['unit_id' => $this->unit->id, 'rank_id' => $this->personnel->rank_id]);
    $this->monitoring->record($this->personnel, 2026, 10, 67.7);
    $this->monitoring->record($others[0], 2026, 10, 70.0);

    $summary = app(ComplianceService::class)->summary(Personnel::query(), 2026, 10);
    $unitRow = app(ComplianceService::class)->byUnit(userWithRole(Role::SuperAdmin), 2026, 10)->firstWhere(fn ($row) => $row['unit']->is($this->unit));

    expect($summary)->toBe(['total' => 4, 'recorded' => 2, 'pending' => 2, 'percent' => 50.0])
        ->and($unitRow['percent'])->toBe(50.0)
        ->and($unitRow['meets_target'])->toBeFalse();
});
