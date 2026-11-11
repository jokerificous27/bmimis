<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\BmiAssessment;
use App\Models\Personnel;
use App\Services\MonthlyMonitoringService;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable official forms (A4). "Save as PDF" is done from the browser print dialog.
 */
class PrintController extends Controller
{
    public function __construct(private Settings $settings) {}

    /**
     * Weigh-in QR cards: one personnel (?personnel_id=) or every active personnel the user may see (?unit_id=).
     */
    public function qrCards(Request $request): View
    {
        $user = $request->user();

        if ($request->filled('personnel_id')) {
            $personnel = Personnel::query()->visibleTo($user)->findOrFail($request->integer('personnel_id'));
            $this->authorize('printReports', $personnel);
            $cards = $personnel->newCollection([$personnel]);
        } else {
            abort_unless($user->hasPermission(Permission::EncodeMonitoring) || $user->hasPermission(Permission::ManagePersonnel) || $user->hasPermission(Permission::GenerateReports), 403);

            $cards = Personnel::query()->visibleTo($user)->active()
                ->when($request->filled('unit_id'), fn ($query) => $query->where('unit_id', $request->integer('unit_id')))
                ->orderBy('unit_id')->orderBy('last_name')->orderBy('first_name')
                ->get();
        }

        $cards->each(fn (Personnel $personnel) => $personnel->qrPayload());
        $cards->load(['rank:id,abbreviation', 'unit:id,code,name']);

        ActivityLog::record('report.printed', newValues: ['document' => 'Weigh-in QR cards', 'count' => $cards->count()], description: 'Opened print preview: Weigh-in QR cards');

        return view('print.qr-cards', ['cards' => $cards, 'settings' => $this->settings]);
    }

    public function bmiForm(Request $request, BmiAssessment $assessment): View
    {
        $this->authorize('print', $assessment);

        $assessment->load(['personnel.rank', 'unit', 'rank', 'encoder', 'certifier', 'interventionPackage']);
        $this->logPrint('Individual BMI Form', $assessment->personnel_id, $assessment);

        return view('print.bmi-form', [
            'assessment' => $assessment,
            'copies' => $request->integer('copies', 2) === 1 ? 1 : 2,
            'settings' => $this->settings,
        ]);
    }

    public function monitoringForm(Request $request, Personnel $personnel, MonthlyMonitoringService $monitoring): View
    {
        $this->authorize('printReports', $personnel);

        $year = $request->integer('year', (int) today()->format('Y'));
        $assessment = $this->assessmentFor($request, $personnel, $year);

        $this->logPrint('Individual BMI Monitoring Form', $personnel->id, $assessment);

        return view('print.monitoring-form', [
            'personnel' => $personnel->load(['rank', 'unit']),
            'assessment' => $assessment?->load(['certifier', 'interventionPackage', 'unit', 'rank']),
            'year' => $year,
            'sheet' => $monitoring->yearSheet($personnel, $year),
            'settings' => $this->settings,
        ]);
    }

    public function profile(Request $request, Personnel $personnel, MonthlyMonitoringService $monitoring): View
    {
        $this->authorize('printReports', $personnel);

        $year = $request->integer('year', (int) today()->format('Y'));
        $personnel->load(['rank', 'unit', 'latestAssessment.interventionPackage', 'assignments.unit', 'assignments.rank', 'interventions.package']);

        $this->logPrint('Consolidated BMI Profile', $personnel->id);

        return view('print.profile', [
            'personnel' => $personnel,
            'assessments' => $personnel->assessments()->official()->orderByDesc('assessed_on')->get(),
            'year' => $year,
            'sheet' => $monitoring->yearSheet($personnel, $year),
            'settings' => $this->settings,
        ]);
    }

    private function assessmentFor(Request $request, Personnel $personnel, int $year): ?BmiAssessment
    {
        if ($request->filled('assessment')) {
            $assessment = $personnel->assessments()->official()->findOrFail($request->integer('assessment'));
            $this->authorize('print', $assessment);

            return $assessment;
        }

        return $personnel->assessments()->official()
            ->whereDate('assessed_on', '<=', CarbonImmutable::create($year, 12, 31))
            ->orderByDesc('assessed_on')->orderByDesc('id')
            ->first();
    }

    private function logPrint(string $document, int $personnelId, ?BmiAssessment $assessment = null): void
    {
        ActivityLog::record('report.printed', $assessment, $personnelId, newValues: ['document' => $document], description: "Opened print preview: {$document}");
    }
}
