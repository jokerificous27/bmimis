<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StandardType;
use App\Livewire\Admin\Permissions;
use App\Livewire\Admin\Standards;
use App\Livewire\Admin\Users;
use App\Livewire\Personnel\Import;
use App\Models\ActivityLog;
use App\Models\BmiStandard;
use App\Models\Personnel;
use App\Models\Unit;
use App\Notifications\ComplianceDeadlineApproaching;
use App\Notifications\MonitoringDue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->travelTo('2026-10-20 09:00');
    $this->admin = userWithRole(Role::SuperAdmin);
    $this->unit = Unit::where('code', 'ITMS')->first();
});

it('lets administrators configure encoder permissions', function () {
    $encoder = userWithRole(Role::HealthServiceEncoder);
    expect($encoder->hasPermission(Permission::EncodeMonitoring))->toBeTrue();

    $component = Livewire::actingAs($this->admin)->test(Permissions::class);
    $component->set('matrix.health_service_encoder', array_values(array_diff($component->get('matrix.health_service_encoder'), [Permission::EncodeMonitoring->value])))
        ->call('save');

    expect($encoder->fresh()->hasPermission(Permission::EncodeMonitoring))->toBeFalse()
        ->and(ActivityLog::where('action', 'permissions.updated')->exists())->toBeTrue();

    $this->actingAs($encoder->fresh())->get(route('monitoring.index'))->assertOk();
    expect($encoder->fresh()->can('encodeMonitoring', personnelIn($this->unit)))->toBeFalse();
});

it('keeps audit trail entries immutable', function () {
    $log = ActivityLog::record('test.entry', description: 'Test');

    expect(fn () => $log->update(['description' => 'Changed']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class)
        ->and(ActivityLog::find($log->id)->description)->toBe('Test');
});

it('records sign-ins in the audit trail', function () {
    $user = userWithRole(Role::Personnel);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect(ActivityLog::where('action', 'auth.login')->where('user_id', $user->id)->exists())->toBeTrue()
        ->and($user->fresh()->last_login_at)->not->toBeNull();
});

it('validates an import file and reports errors before committing', function () {
    $csv = "employee_number,rank,last_name,first_name,middle_name,suffix,sex,date_of_birth,unit,position,contact_number,email\n"
        ."PNP-100001,PCpl,Valdez,Ana,Cruz,,F,1996-02-11,ITMS,Clerk,09170000000,\n"
        ."PNP-100002,XYZ,Lopez,Ben,,,M,1990-01-01,ITMS,,,\n"
        ."PNP-100001,Pat,Dup,Licate,,,M,1990-01-01,ITMS,,,\n";

    $component = Livewire::actingAs($this->admin)->test(Import::class)
        ->set('type', 'personnel')
        ->set('file', UploadedFile::fake()->createWithContent('personnel.csv', $csv))
        ->call('analyze');

    $result = $component->get('result');

    expect($result['total'])->toBe(3)
        ->and($result['valid'])->toHaveCount(1)
        ->and($result['errors'])->toHaveKeys([3, 4])
        ->and(Personnel::count())->toBe(0);

    $component->call('commit');

    expect(Personnel::where('employee_number', 'PNP-100001')->exists())->toBeTrue()
        ->and(Personnel::count())->toBe(1)
        ->and(ActivityLog::where('action', 'import.personnel')->exists())->toBeTrue();
});

it('sends monthly monitoring reminders to personnel without a weight', function () {
    Notification::fake();
    $withAccount = userWithRole(Role::Personnel);
    personnelIn($this->unit, ['user_id' => $withAccount->id]);
    $representative = userWithRole(Role::UnitRepresentative, $this->unit);

    $this->artisan('bmi:send-reminders', ['--force' => true])->assertSuccessful();

    Notification::assertSentTo($withAccount, MonitoringDue::class);
    Notification::assertSentTo($representative, ComplianceDeadlineApproaching::class);
});

it('rejects overlapping classification bands', function () {
    $standard = BmiStandard::where('type', StandardType::Pnp->value)->first();

    Livewire::actingAs($this->admin)->test(Standards::class)
        ->call('select', $standard->id)
        ->set('bands.1.min_bmi', '18.0')
        ->call('save')
        ->assertHasErrors('bands.1.min_bmi');
});

it('prevents removing the last active super administrator', function () {
    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('toggleActive', $this->admin->id)
        ->assertHasErrors('role');

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('requires unit representatives to have an assigned unit', function () {
    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('create')
        ->set('name', 'Rep Without Unit')
        ->set('email', 'rep@example.test')
        ->set('role', Role::UnitRepresentative->value)
        ->set('password', 'password')
        ->call('save')
        ->assertHasErrors('unit_id');
});
