<?php

use App\Http\Controllers\AssessmentPhotoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MyQrController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WeighInPhotoController;
use App\Livewire\Admin;
use App\Livewire\Analytics;
use App\Livewire\Assessments;
use App\Livewire\Compliance;
use App\Livewire\Dashboard;
use App\Livewire\Interventions;
use App\Livewire\Monitoring;
use App\Livewire\Notifications;
use App\Livewire\Personnel;
use App\Livewire\Reports;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::livewire('notifications', Notifications\Index::class)->name('notifications.index');

    // Personnel self-service. These reuse the staff components; policies restrict data to the signed-in personnel.
    Route::prefix('my')->name('my.')->group(function () {
        Route::livewire('dashboard', Dashboard\MyDashboard::class)->name('dashboard');
        Route::livewire('profile', Personnel\Show::class)->name('profile');
        Route::get('qr', MyQrController::class)->name('qr');
        Route::livewire('monitoring', Monitoring\PersonnelYear::class)->name('monitoring');
        Route::livewire('assessments', Assessments\Index::class)->name('assessments');
        Route::livewire('assessments/{assessment}', Assessments\Show::class)->name('assessments.show');
    });

    Route::livewire('health-service/dashboard', Dashboard\HealthServiceDashboard::class)
        ->middleware('permission:records.view_all,compliance.view')
        ->name('dashboard.health-service');
    Route::livewire('unit-monitoring', Dashboard\UnitDashboard::class)
        ->middleware('permission:compliance.view')
        ->name('dashboard.unit');

    Route::livewire('personnel', Personnel\Index::class)->name('personnel.index');
    Route::livewire('personnel/create', Personnel\Form::class)->name('personnel.create');
    Route::livewire('personnel/import', Personnel\Import::class)->middleware('permission:personnel.import')->name('personnel.import');
    Route::livewire('personnel/{personnel}', Personnel\Show::class)->name('personnel.show');
    Route::livewire('personnel/{personnel}/edit', Personnel\Form::class)->name('personnel.edit');

    Route::livewire('assessments', Assessments\Index::class)->name('assessments.index');
    Route::livewire('assessments/create', Assessments\Form::class)->name('assessments.create');
    Route::livewire('assessments/{assessment}', Assessments\Show::class)->name('assessments.show');
    Route::livewire('assessments/{assessment}/edit', Assessments\Form::class)->name('assessments.edit');
    Route::get('assessments/{assessment}/photos/{view}', AssessmentPhotoController::class)
        ->whereIn('view', ['front', 'right', 'left'])
        ->name('assessments.photo');

    Route::livewire('monitoring', Monitoring\Index::class)->middleware('permission:monitoring.encode,compliance.view')->name('monitoring.index');
    Route::livewire('monitoring/station', Monitoring\Station::class)->middleware('permission:monitoring.encode')->name('monitoring.station');
    Route::get('monitoring/records/{record}/photo', WeighInPhotoController::class)->name('monitoring.photo');
    Route::livewire('monitoring/{personnel}', Monitoring\PersonnelYear::class)->name('monitoring.personnel');

    Route::livewire('interventions', Interventions\Index::class)->name('interventions.index');
    Route::livewire('interventions/{intervention}', Interventions\Show::class)->name('interventions.show');

    Route::livewire('compliance', Compliance\Index::class)->middleware('permission:compliance.view')->name('compliance.index');
    Route::livewire('analytics', Analytics\Index::class)->middleware('permission:analytics.view')->name('analytics.index');

    Route::livewire('reports', Reports\Index::class)->middleware('permission:reports.generate')->name('reports.index');
    Route::get('reports/{report}/print', [ReportController::class, 'print'])->middleware('permission:reports.generate')->name('reports.print');
    Route::get('reports/{report}/export', [ReportController::class, 'export'])->middleware('permission:records.export')->name('reports.export');

    Route::prefix('print')->name('print.')->group(function () {
        Route::get('assessments/{assessment}/bmi-form', [PrintController::class, 'bmiForm'])->name('bmi-form');
        Route::get('personnel/{personnel}/monitoring-form', [PrintController::class, 'monitoringForm'])->name('monitoring-form');
        Route::get('personnel/{personnel}/profile', [PrintController::class, 'profile'])->name('profile');
        Route::get('qr-cards', [PrintController::class, 'qrCards'])->name('qr-cards');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::livewire('users', Admin\Users::class)->middleware('permission:users.manage')->name('users');
        Route::livewire('permissions', Admin\Permissions::class)->middleware('permission:users.manage')->name('permissions');
        Route::livewire('units', Admin\Units::class)->middleware('permission:units.manage')->name('units');
        Route::livewire('ranks', Admin\Ranks::class)->middleware('permission:ranks.manage')->name('ranks');
        Route::livewire('bmi-standards', Admin\Standards::class)->middleware('permission:standards.manage')->name('standards');
        Route::livewire('intervention-packages', Admin\Packages::class)->middleware('permission:packages.manage')->name('packages');
        Route::livewire('audit-trail', Admin\AuditTrail::class)->middleware('permission:audit.view')->name('audit');
        Route::livewire('settings', Admin\SystemSettings::class)->middleware('permission:settings.manage')->name('settings');
    });
});

require __DIR__.'/settings.php';
