<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Services\AssessmentService;
use App\Services\BmiStatistics;
use App\Services\MonthlyMonitoringService;
use App\Services\PersonnelQuery;
use App\Services\Reports\ReportBuilder;

beforeEach(function () {
    seedReferenceData();
    $this->travelTo('2026-10-20 09:00');

    $this->admin = userWithRole(Role::HealthServiceAdmin);
    $this->actingAs($this->admin);

    $this->itms = Unit::where('code', 'ITMS')->first();
    $this->finance = Unit::where('code', 'FS')->first();

    $this->normal = personnelIn($this->itms, ['last_name' => 'Normalis']);
    $this->overweight = personnelIn($this->itms, ['last_name' => 'Heavier']);
    $this->noRecord = personnelIn($this->finance, ['last_name' => 'Pendiente']);

    $service = app(AssessmentService::class);
    $service->create($this->normal, ['assessed_on' => '2026-10-06', 'height_cm' => 169, 'weight_kg' => 67.7], submit: true, recordMonthlyWeight: true);
    $service->create($this->overweight, ['assessed_on' => '2026-10-07', 'height_cm' => 169, 'weight_kg' => 80], submit: true, recordMonthlyWeight: true);
    app(MonthlyMonitoringService::class)->record($this->normal, 2026, 9, 68.95);
});

it('builds the monthly weight monitoring report with change from the previous month', function () {
    $report = app(ReportBuilder::class)->build('monthly-monitoring', $this->admin, ['year' => 2026, 'month' => 10]);
    $rows = collect($report['sections'][0]['rows'])->keyBy(fn ($row) => explode(' ', $row[0], 2)[1] ?? $row[0]);
    $normalRow = collect($report['sections'][0]['rows'])->first(fn ($row) => str_contains($row[0], 'Normalis'));

    expect($report['summary'])->toMatchArray(['Personnel' => 3, 'Recorded' => 2, 'Not recorded' => 1, 'Compliance' => '66.7%'])
        ->and($normalRow[2])->toEqual(67.7)
        ->and($normalRow[4])->toEqual(23.7)
        ->and($normalRow[7])->toEqual(-1.25)
        ->and($rows)->toHaveCount(3);
});

it('computes unit-level compliance statistics', function () {
    $report = app(ReportBuilder::class)->build('unit-compliance', $this->admin, ['year' => 2026, 'month' => 10]);
    $rows = collect($report['sections'][0]['rows'])->keyBy(1);

    expect($rows['ITMS'][2])->toBe(2)->and($rows['ITMS'][3])->toBe(2)->and($rows['ITMS'][5])->toEqual(100.0)
        ->and($rows['FS'][3])->toBe(0)->and($rows['FS'][6])->toBe('Below target')
        ->and($report['summary']['Overall compliance'])->toBe('66.7%');
});

it('summarizes BMI classifications from live records', function () {
    $data = app(BmiStatistics::class)->dashboard(PersonnelQuery::for($this->admin), 2026, 10);

    expect($data['stats'])->toMatchArray([
        'total' => 3,
        'with_records' => 2,
        'normal' => 1,
        'above' => 1,
        'below' => 0,
        'requiring_intervention' => 1,
        'monthly_completed' => 2,
        'monthly_pending' => 1,
        'compliance_percent' => 66.7,
    ])->and($data['charts']['pnp_distribution'])->toBe(['Normal' => 1, 'Overweight' => 1]);
});

it('lists personnel requiring intervention', function () {
    $report = app(ReportBuilder::class)->build('intervention', $this->admin, []);

    expect($report['sections'][0]['rows'])->toHaveCount(1)
        ->and($report['sections'][0]['rows'][0][0])->toContain('Heavier')
        ->and($report['sections'][0]['rows'][0][6])->toBe('Not yet assigned');
});

it('lists pending monthly monitoring for a unit', function () {
    $report = app(ReportBuilder::class)->build('pending-monitoring', $this->admin, ['year' => 2026, 'month' => 10, 'unit_id' => $this->finance->id]);

    expect($report['sections'][0]['rows'])->toHaveCount(1)
        ->and($report['sections'][0]['rows'][0][1])->toContain('Pendiente');
});

it('exports reports to Excel and records the export in the audit trail', function () {
    $response = $this->get(route('reports.export', ['report' => 'monthly-monitoring', 'year' => 2026, 'month' => 10]));

    $response->assertOk()->assertDownload();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml')
        ->and(ActivityLog::where('action', 'report.exported')->where('user_id', $this->admin->id)->exists())->toBeTrue();
});

it('exports the BMI upload file in the template layout', function () {
    $this->normal->update(['employee_number' => '0012345']);
    $this->normal->latestAssessment->forceFill(['waist_cm' => 82.5, 'hip_cm' => 96, 'wrist_cm' => 16.5])->save();

    $response = $this->get(route('reports.export', ['report' => 'bmi-upload', 'year' => 2026, 'month' => 10]));
    $response->assertOk()->assertDownload('BMI-october-2026.xlsx');

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());
    $workbook = $zip->getFromName('xl/workbook.xml');
    $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
    $sheetCount = $zip->getFromName('xl/worksheets/sheet2.xml');
    $zip->close();

    $rows = collect();

    foreach ($sheet->sheetData->row as $row) {
        $cells = [];

        foreach ($row->c as $cell) {
            $cells[preg_replace('/\d+/', '', (string) $cell['r'])] = isset($cell->is) ? (string) $cell->is->t : (string) $cell->v;
        }

        $rows->push($cells);
    }

    expect($workbook)->toContain('name="Sheet1"')
        ->and($sheetCount)->toBeFalse()
        ->and(array_values($rows[0]))->toBe(['AccountNumber', 'ApplicableYear', 'ApplicableMonth', 'Height (cm)', 'Weight (kg)', 'Hip (cm)', 'Wrist (cm)', 'Waist (cm)'])
        ->and($rows)->toHaveCount(3)
        ->and($rows->firstWhere('A', '0012345'))->toBe(['A' => '0012345', 'B' => '2026', 'C' => '10', 'D' => '169', 'E' => '67.7', 'F' => '96', 'G' => '16.5', 'H' => '82.5']);
});

it('prints the individual BMI form with the official layout and computed values', function () {
    $assessment = $this->normal->latestAssessment;

    $this->get(route('print.bmi-form', $assessment))
        ->assertOk()
        ->assertSeeInOrder(['BODY MASS INDEX (BMI) FORM', 'Height (cm)', '169', 'Weight (kilogram)', '67.70', 'BMI Result', '23.7'])
        ->assertSee('IBW:')
        ->assertSee('52.8 kgs to 71.4 kgs')
        ->assertSee('Weight to lose:')
        ->assertSee('Maintain');
});

it('requires a personnel selection for individual reports', function () {
    $this->get(route('reports.print', ['report' => 'individual-history']))->assertStatus(422);
});
