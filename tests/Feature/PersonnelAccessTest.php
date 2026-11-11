<?php

use App\Enums\Role;
use App\Livewire\Personnel\Index;
use App\Models\BmiAssessment;
use App\Models\Personnel;
use App\Models\Unit;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();

    $this->itms = Unit::where('code', 'ITMS')->first();
    $this->finance = Unit::where('code', 'FS')->first();

    $this->ownerUser = userWithRole(Role::Personnel);
    $this->owner = personnelIn($this->itms, ['user_id' => $this->ownerUser->id]);
    $this->colleague = personnelIn($this->itms);
    $this->outsider = personnelIn($this->finance);

    $this->ownAssessment = BmiAssessment::factory()->for($this->owner)->create(['unit_id' => $this->itms->id]);
    $this->otherAssessment = BmiAssessment::factory()->for($this->outsider)->create(['unit_id' => $this->finance->id]);
});

it('lets personnel view only their own BMI profile and records', function () {
    $this->actingAs($this->ownerUser);

    $this->get(route('my.profile'))->assertOk()->assertSee($this->owner->employee_number);
    $this->get(route('my.assessments.show', $this->ownAssessment))->assertOk();
    $this->get(route('print.bmi-form', $this->ownAssessment))->assertOk();
    $this->get(route('print.monitoring-form', $this->owner))->assertOk();
});

it('prevents personnel from opening another personnel record by changing the URL', function () {
    $this->actingAs($this->ownerUser);

    $this->get(route('personnel.show', $this->colleague))->assertForbidden();
    $this->get(route('personnel.show', $this->outsider))->assertForbidden();
    $this->get(route('assessments.show', $this->otherAssessment))->assertForbidden();
    $this->get(route('my.assessments.show', $this->otherAssessment))->assertForbidden();
    $this->get(route('print.bmi-form', $this->otherAssessment))->assertForbidden();
    $this->get(route('print.monitoring-form', $this->outsider))->assertForbidden();
    $this->get(route('print.profile', $this->outsider))->assertForbidden();
    $this->get(route('monitoring.personnel', $this->outsider))->assertForbidden();
    $this->get(route('assessments.photo', [$this->otherAssessment, 'front']))->assertForbidden();
});

it('blocks personnel from staff modules, reports and exports', function () {
    $this->actingAs($this->ownerUser);

    $this->get(route('personnel.index'))->assertForbidden();
    $this->get(route('assessments.index'))->assertForbidden();
    $this->get(route('dashboard.health-service'))->assertForbidden();
    $this->get(route('reports.index'))->assertForbidden();
    $this->get(route('reports.export', ['report' => 'personnel-directory']))->assertForbidden();
    $this->get(route('admin.users'))->assertForbidden();
    $this->get(route('admin.audit'))->assertForbidden();
});

it('limits unit representatives to personnel of their assigned unit', function () {
    $representative = userWithRole(Role::UnitRepresentative, $this->itms);
    $this->actingAs($representative);

    $this->get(route('personnel.show', $this->colleague))->assertOk();
    $this->get(route('personnel.show', $this->outsider))->assertForbidden();
    $this->get(route('assessments.show', $this->otherAssessment))->assertForbidden();

    Livewire::test(Index::class)
        ->assertSee($this->colleague->employee_number)
        ->assertDontSee($this->outsider->employee_number);

    expect(Personnel::query()->visibleTo($representative)->pluck('id')->all())
        ->toContain($this->colleague->id)
        ->not->toContain($this->outsider->id);
});

it('restricts unit representative reports to their unit', function () {
    $representative = userWithRole(Role::UnitRepresentative, $this->itms);

    $this->actingAs($representative)
        ->get(route('reports.print', ['report' => 'personnel-directory', 'unit_id' => $this->finance->id]))
        ->assertOk()
        ->assertDontSee($this->outsider->employee_number);
});

it('gives Health Service administrators access to all personnel', function () {
    $this->actingAs(userWithRole(Role::HealthServiceAdmin));

    $this->get(route('personnel.show', $this->outsider))->assertOk();
    $this->get(route('assessments.show', $this->otherAssessment))->assertOk();
    $this->get(route('print.bmi-form', $this->otherAssessment))->assertOk();
});

it('hides draft assessments from personnel', function () {
    $draft = BmiAssessment::factory()->for($this->owner)->draft()->create();

    $this->actingAs($this->ownerUser)->get(route('my.assessments.show', $draft))->assertForbidden();
});

it('signs out deactivated accounts', function () {
    $this->ownerUser->forceFill(['is_active' => false])->save();

    $this->actingAs($this->ownerUser)->get(route('my.profile'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('rejects sign-in for deactivated accounts', function () {
    $this->ownerUser->forceFill(['is_active' => false])->save();

    $this->post(route('login.store'), ['email' => $this->ownerUser->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
