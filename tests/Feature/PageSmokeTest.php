<?php

use App\Enums\Role;
use App\Models\InterventionPackage;
use App\Models\Unit;
use App\Services\AssessmentService;
use App\Services\InterventionService;
use App\Services\MonthlyMonitoringService;

beforeEach(function () {
    seedReferenceData();
    $this->travelTo('2026-10-20 09:00');

    $this->admin = userWithRole(Role::SuperAdmin);
    $this->actingAs($this->admin);

    $unit = Unit::where('code', 'ITMS')->first();
    $this->personnel = personnelIn($unit);
    $this->assessment = app(AssessmentService::class)->create($this->personnel, [
        'assessed_on' => '2026-10-06', 'height_cm' => 169, 'weight_kg' => 80, 'waist_cm' => 90, 'hip_cm' => 95, 'wrist_cm' => 16,
    ], submit: true, recordMonthlyWeight: true);
    app(MonthlyMonitoringService::class)->record($this->personnel, 2026, 9, 81.2);
    $this->intervention = app(InterventionService::class)->assign($this->personnel, InterventionPackage::first(), today());
});

it('renders every staff page with live data', function (string $route, array $parameters = []) {
    $parameters = array_map(fn ($value) => is_string($value) && str_starts_with($value, '@') ? $this->{substr($value, 1)} : $value, $parameters);

    $this->get(route($route, $parameters))->assertOk();
})->with([
    ['dashboard.health-service'],
    ['dashboard.unit'],
    ['notifications.index'],
    ['personnel.index'],
    ['personnel.index', ['mode' => 'profiles']],
    ['personnel.create'],
    ['personnel.import'],
    ['personnel.show', ['personnel' => '@personnel']],
    ['personnel.show', ['personnel' => '@personnel', 'tab' => 'progress']],
    ['personnel.show', ['personnel' => '@personnel', 'tab' => 'assessments']],
    ['personnel.show', ['personnel' => '@personnel', 'tab' => 'monitoring']],
    ['personnel.show', ['personnel' => '@personnel', 'tab' => 'interventions']],
    ['personnel.show', ['personnel' => '@personnel', 'tab' => 'history']],
    ['personnel.edit', ['personnel' => '@personnel']],
    ['assessments.index'],
    ['assessments.create'],
    ['assessments.show', ['assessment' => '@assessment']],
    ['assessments.edit', ['assessment' => '@assessment']],
    ['monitoring.index'],
    ['monitoring.personnel', ['personnel' => '@personnel']],
    ['interventions.index'],
    ['interventions.show', ['intervention' => '@intervention']],
    ['compliance.index'],
    ['analytics.index'],
    ['analytics.index', ['tab' => 'trends']],
    ['analytics.index', ['tab' => 'quality']],
    ['reports.index'],
    ['reports.index', ['category' => 'summary']],
    ['admin.users'],
    ['admin.permissions'],
    ['admin.units'],
    ['admin.ranks'],
    ['admin.standards'],
    ['admin.packages'],
    ['admin.audit'],
    ['admin.settings'],
]);

it('renders every printable report', function (string $report) {
    $this->get(route('reports.print', ['report' => $report, 'year' => 2026, 'month' => 10, 'personnel_id' => $this->personnel->id]))
        ->assertOk()
        ->assertSee('Republic of the Philippines');
})->with(['individual-history', 'monthly-monitoring', 'pending-monitoring', 'annual-monitoring', 'weight-progress', 'unit-compliance', 'summary', 'classification-distribution', 'intervention', 'personnel-directory']);

it('renders the official forms', function () {
    $this->get(route('print.bmi-form', $this->assessment))->assertOk()->assertSee('BODY MASS INDEX (BMI) FORM')->assertSee('28.0');
    $this->get(route('print.monitoring-form', ['personnel' => $this->personnel, 'year' => 2026]))->assertOk()->assertSee('INDIVIDUAL BMI MONITORING FORM')->assertSee('81.20');
    $this->get(route('print.profile', $this->personnel))->assertOk()->assertSee('CONSOLIDATED PERSONNEL BMI PROFILE');
});

it('renders the personnel self-service pages', function () {
    $user = userWithRole(Role::Personnel);
    $this->personnel->forceFill(['user_id' => $user->id])->save();
    $this->actingAs($user);

    foreach (['my.dashboard', 'my.profile', 'my.monitoring', 'my.assessments', 'notifications.index'] as $route) {
        $this->get(route($route))->assertOk();
    }
});
