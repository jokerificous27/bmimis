<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportCatalog;
use App\Support\Settings;
use App\Support\Spreadsheet\XlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private ReportBuilder $builder) {}

    public function print(Request $request, string $report, Settings $settings): View|RedirectResponse
    {
        abort_unless(ReportCatalog::find($report), 404);

        if (ReportCatalog::isForm($report)) {
            return $this->redirectToForm($request, $report);
        }

        $data = $this->build($request, $report);

        ActivityLog::record('report.generated', personnelId: $request->integer('personnel_id') ?: null, newValues: ['report' => $report, 'filters' => $data['meta']], description: "Generated report: {$data['title']}");

        return view('print.report', ['report' => $data, 'settings' => $settings, 'exportUrl' => $request->user()->can('records.export') ? route('reports.export', ['report' => $report] + $request->query()) : null]);
    }

    public function export(Request $request, string $report): BinaryFileResponse
    {
        abort_unless(ReportCatalog::find($report) && ! ReportCatalog::isForm($report), 404);

        $data = $this->build($request, $report);
        $writer = new XlsxWriter;
        $filename = Str::slug($data['title']).'-'.now()->format('Ymd-His').'.xlsx';

        if (ReportCatalog::isUploadFile($report)) {
            // Exactly the template's layout: one sheet "Sheet1", header in row 1, nothing else.
            $section = $data['sections'][0];
            $writer->addPlainSheet('Sheet1', $section['columns'], $section['rows'], 24.43);
            $filename = 'BMI-'.Str::slug((string) $data['subtitle']).'.xlsx';
        } else {
            foreach ($this->builder->sheets($data) as $sheet) {
                $writer->addSheet($sheet['name'], $sheet['columns'], $sheet['rows'], $sheet['title']);
            }

            if ($data['summary'] !== []) {
                $writer->addSheet('Summary', ['Item', 'Value'], collect($data['summary'] + $data['meta'])->map(fn ($value, $label) => [$label, $value])->values()->all(), $data['title']);
            }
        }

        ActivityLog::record('report.exported', personnelId: $request->integer('personnel_id') ?: null, newValues: ['report' => $report, 'filters' => $data['meta']], description: "Exported to Excel: {$data['title']}");

        return response()->download($writer->save(), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Request $request, string $report): array
    {
        $definition = ReportCatalog::find($report);

        foreach ($definition['requires'] as $required) {
            abort_unless($request->filled($required), 422, __('Please select the required filters.'));
        }

        return $this->builder->build($report, $request->user(), $request->query());
    }

    private function redirectToForm(Request $request, string $report): RedirectResponse
    {
        $personnel = $this->builder->findPersonnel($request->user(), $request->query('personnel_id'));
        $year = $request->integer('year', (int) today()->format('Y'));

        return match ($report) {
            'bmi-form' => ($assessment = $request->filled('assessment_id')
                ? $personnel->assessments()->official()->findOrFail($request->integer('assessment_id'))
                : $personnel->latestAssessment)
                ? redirect()->route('print.bmi-form', $assessment)
                : back()->with('report_error', __('This personnel has no submitted or certified assessment yet.')),
            'monitoring-form' => redirect()->route('print.monitoring-form', ['personnel' => $personnel, 'year' => $year]),
            default => redirect()->route('print.profile', ['personnel' => $personnel, 'year' => $year]),
        };
    }
}
