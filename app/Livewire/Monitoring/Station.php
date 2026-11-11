<?php

namespace App\Livewire\Monitoring;

use App\Enums\Permission;
use App\Livewire\Concerns\ValidatesMeasurements;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use App\Services\MeasurementReviewService;
use App\Services\MonthlyMonitoringService;
use App\Services\WeighIn\ScaleReader;
use App\Services\WeighIn\ScaleReading;
use App\Services\WeighIn\WeighInPhoto;
use App\Support\Settings;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Weigh-in Station: identify personnel from their QR code, take one photo of the scale, confirm the weight.
 * The browser decodes the QR code; the server optionally reads the weight from the photo.
 */
#[Title('Weigh-in Station')]
class Station extends Component
{
    use ValidatesMeasurements, WithFileUploads;

    /**
     * Below this many pixels on the longest edge, LED digits are usually too small to read reliably.
     */
    private const MIN_READABLE_PHOTO_EDGE = 1000;

    #[Locked]
    public ?int $personnelId = null;

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    #[Locked]
    public ?string $pendingPhotoPath = null;

    /**
     * @var array{weight_kg: float|null, confidence: string, displayed_text: string|null, note: string|null}|null
     */
    #[Locked]
    public ?array $reading = null;

    /**
     * Reading of the scale display made on the phone (weight reader "device"), sent before the photo upload.
     *
     * @var array{weight_kg: float|null, confidence: string, displayed_text: string|null, note: string|null}|null
     */
    #[Locked]
    public ?array $deviceReading = null;

    public string $weight = '';

    /**
     * Why the current photo needs a staff check instead of being saved automatically.
     */
    #[Locked]
    public ?string $reviewReason = null;

    /**
     * The last weigh-in saved at this station, shown so staff can spot and correct mistakes.
     *
     * @var array{name: string, weight: float, bmi: float|null, personnel_id: int, year: int, automatic: bool, measurements?: bool, review_record_id: int|null}|null
     */
    #[Locked]
    public ?array $lastSaved = null;

    public string $search = '';

    public string $scanMessage = '';

    /**
     * Whether staff chose to measure waist, hip and wrist again with this weigh-in (null: not answered yet).
     */
    #[Locked]
    public ?bool $updateMeasurements = null;

    public string $waist_cm = '';

    public string $hip_cm = '';

    public string $wrist_cm = '';

    /**
     * A clear reading that would be saved automatically, waiting for the answer to the measurement question.
     */
    #[Locked]
    public bool $autoSaveHeld = false;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermission(Permission::EncodeMonitoring), 403);
    }

    /**
     * Called by the browser with the text decoded from a QR code. Returns the name shown in the camera status.
     */
    public function identify(string $payload): ?string
    {
        $person = Personnel::findByQrPayload($payload, Auth::user());

        if (! $person) {
            $this->scanMessage = __('QR code not recognised. It may be an old card or another unit\'s personnel — search by name instead.');

            return null;
        }

        $this->selectPersonnel($person);

        return $person->rank_name;
    }

    public function choosePersonnel(int $personnelId): void
    {
        $this->selectPersonnel(Personnel::query()->visibleTo(Auth::user())->findOrFail($personnelId));
    }

    private function selectPersonnel(Personnel $person): void
    {
        $this->authorize('encodeMonitoring', $person);

        if ($this->personnelId !== $person->id) {
            $this->reset('updateMeasurements', 'waist_cm', 'hip_cm', 'wrist_cm', 'autoSaveHeld');
        }

        $this->personnelId = $person->id;
        $this->search = '';
        $this->scanMessage = '';
        unset($this->personnel, $this->canUpdateMeasurements);
    }

    /**
     * Staff who encode assessments are asked, before the weight is saved, whether to measure waist,
     * hip and wrist again. Needs a height on record, which the new assessment carries over.
     */
    #[Computed]
    public function canUpdateMeasurements(): bool
    {
        return $this->personnel !== null
            && Auth::user()->hasPermission(Permission::EncodeAssessments)
            && $this->personnel->latestAssessment?->height_cm !== null;
    }

    /**
     * "Yes, update measurements": open the fields with the last measurements filled in.
     */
    public function startMeasurements(): void
    {
        abort_unless($this->canUpdateMeasurements, 403);

        $last = $this->personnel->latestAssessment;
        $this->waist_cm = $this->decimal($last?->waist_cm);
        $this->hip_cm = $this->decimal($last?->hip_cm);
        $this->wrist_cm = $this->decimal($last?->wrist_cm);
        $this->updateMeasurements = true;
        $this->resetValidation();
    }

    /**
     * Back to the question without saving.
     */
    public function cancelMeasurements(): void
    {
        $this->reset('updateMeasurements', 'waist_cm', 'hip_cm', 'wrist_cm');
        $this->resetValidation();
    }

    /**
     * "No, save weight only".
     */
    public function saveWeightOnly(MonthlyMonitoringService $monitoring): void
    {
        $this->updateMeasurements = false;
        $this->persist($monitoring, automatic: $this->autoSaveHeld);
    }

    /**
     * How the weight is read: "device" (on the phone), "ai" (Anthropic API, when configured) or "off".
     */
    public function readerMode(): string
    {
        return match (app(Settings::class)->get('weight_reader')) {
            'device' => 'device',
            'ai' => app(ScaleReader::class)->isEnabled() ? 'ai' : 'off',
            default => 'off',
        };
    }

    /**
     * Number of decimals the scale display shows (System Settings), or null when it may show one or two.
     */
    private function scaleDecimals(): ?int
    {
        return match ((string) app(Settings::class)->get('scale_decimals')) {
            '1' => 1,
            '2' => 2,
            default => null,
        };
    }

    /**
     * Called by the browser with what it read from the scale's LED display, just before uploading the photo.
     * This is the same trust as staff typing the weight: auto-save still applies every server-side check.
     */
    public function useDeviceReading(mixed $weight, string $confidence, string $text, int $photoWidth = 0, int $photoHeight = 0, int $stableFrames = 0): void
    {
        if ($this->readerMode() !== 'device') {
            return;
        }

        $valid = is_numeric($weight) && (float) $weight > 0 && (float) $weight < 1000;
        $tooSmall = max($photoWidth, $photoHeight) > 0 && max($photoWidth, $photoHeight) < self::MIN_READABLE_PHOTO_EDGE;

        $note = match (true) {
            $valid && $stableFrames > 1 => __('Read live from the scale display on this device (same reading in :count frames in a row).', ['count' => min($stableFrames, 99)]),
            $valid => __('Read from the scale display on this device.'),
            $tooSmall => __('This photo is only :width × :height pixels, too small to read the scale digits. Take the photo directly with the phone camera at full resolution (not a screenshot, edited or chat-compressed image), or type the weight.', ['width' => $photoWidth, 'height' => $photoHeight]),
            default => __('The scale display could not be read. Type the weight shown on the scale, or retake the photo straight-on without glare.'),
        };

        // A reading with a different number of decimals than the scale shows is never trusted.
        $decimals = $this->scaleDecimals();
        $shownDecimals = str_contains($text, '.') ? strlen(substr(strrchr($text, '.'), 1)) : 0;
        $certain = $confidence === 'high' && ($decimals === null || $shownDecimals === $decimals);

        $this->deviceReading = [
            'weight_kg' => $valid ? round((float) $weight, 2) : null,
            'confidence' => $valid && $certain ? 'high' : ($valid ? 'low' : 'none'),
            'displayed_text' => mb_substr(preg_replace('/[^0-9.]/', '', $text) ?? '', 0, 12) ?: null,
            'note' => $note,
        ];
    }

    /**
     * Normalize the uploaded photo, keep it privately until saved, and read the scale if enabled.
     */
    public function updatedPhoto(): void
    {
        $mode = $this->readerMode();

        $this->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360']]);

        try {
            $jpeg = WeighInPhoto::normalize($this->photo->getRealPath());
        } catch (RuntimeException $exception) {
            $this->addError('photo', $exception->getMessage());

            return;
        }

        $this->discardPendingPhoto();
        $this->pendingPhotoPath = 'weigh-in-photos/pending/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($this->pendingPhotoPath, $jpeg);
        $this->photo = null;

        $this->reading = match ($mode) {
            'ai' => app(ScaleReader::class)->read($jpeg)->toArray(),
            'device' => $this->deviceReading ?? ScaleReading::unavailable(__('The scale display could not be read automatically.'))->toArray(),
            default => null,
        };
        $this->deviceReading = null;
        $this->reviewReason = null;
        $this->autoSaveHeld = false;

        if (($this->reading['weight_kg'] ?? null) !== null) {
            $this->weight = number_format((float) $this->reading['weight_kg'], 2, '.', '');
        }

        if ($mode !== 'off' && app(Settings::class)->bool('station_auto_save')) {
            $this->reviewReason = $this->autoSaveBlocker();

            if ($this->reviewReason === null && $this->canUpdateMeasurements && $this->updateMeasurements === null) {
                $this->autoSaveHeld = true;
            } elseif ($this->reviewReason === null) {
                $this->persist(app(MonthlyMonitoringService::class), automatic: true);
            }
        }
    }

    /**
     * Why the reading must be checked by staff instead of being saved automatically, or null when it is safe.
     */
    private function autoSaveBlocker(): ?string
    {
        $person = $this->personnel;
        $settings = app(Settings::class);

        if (! $person) {
            return __('Nobody identified: the QR code was not found in the photo. Search the personnel by name, or retake the photo.');
        }

        if ($this->thisMonthRecord) {
            return __('Already weighed this month (:weight kg). If this is a re-weigh, check the new reading and replace it.', ['weight' => number_format($this->thisMonthRecord->weight_kg, 2)]);
        }

        if (($this->reading['weight_kg'] ?? null) === null) {
            return $this->readerMode() === 'device' && filled($this->reading['note'] ?? null)
                ? $this->reading['note']
                : __('The scale display could not be read. Type the weight shown on the scale.');
        }

        if (($this->reading['confidence'] ?? null) !== 'high') {
            return __('The scale reading is not clear enough to save automatically. Check the number against the photo.');
        }

        $weight = (float) $this->reading['weight_kg'];

        if ($weight < $settings->float('weight_min_kg') || $weight > $settings->float('weight_max_kg')) {
            return __('The reading is outside the plausible weight range.');
        }

        $previous = $this->previousRecord();
        $limit = $settings->float('significant_weight_change_kg');

        if ($previous && abs($weight - $previous->weight_kg) > $limit) {
            return __('The reading differs by :difference kg from the last weigh-in (:month). Confirm it is correct.', [
                'difference' => number_format(abs($weight - $previous->weight_kg), 2),
                'month' => $previous->monthName().' '.$previous->year,
            ]);
        }

        return null;
    }

    /**
     * Staff confirmation (the "Confirm and save" button).
     */
    public function save(MonthlyMonitoringService $monitoring): void
    {
        $this->persist($monitoring, automatic: false);
    }

    /**
     * Re-weigh: replace this month's weight with the new reading (staff-confirmed, kept in the audit trail).
     */
    public function replaceThisMonth(MonthlyMonitoringService $monitoring): void
    {
        $person = $this->personnel ?? throw ValidationException::withMessages(['personnel' => __('Scan the personnel\'s QR code or search by name first.')]);
        $record = $this->thisMonthRecord ?? throw ValidationException::withMessages(['weight_kg' => __('There is no weigh-in this month to replace.')]);
        $this->authorize('update', $record);

        $this->validate(['weight' => $this->measurementRule('weight', decimals: 2)], $this->measurementMessages());

        $weight = (float) $this->weight;
        $previousWeight = $record->weight_kg;
        $aiWeight = $this->reading['weight_kg'] ?? null;

        if ($this->pendingPhotoPath && Storage::disk('local')->exists($this->pendingPhotoPath)) {
            $photoPath = 'weigh-in-photos/'.$person->id.'/'.today()->format('Y-m').'-'.Str::random(10).'.jpg';
            Storage::disk('local')->move($this->pendingPhotoPath, $photoPath);
            $record->photo_path = $photoPath;
        }

        $record->fill(['capture_method' => 'station', 'ai_weight_kg' => $aiWeight, 'ai_confidence' => $this->reading['confidence'] ?? null]);

        $monitoring->correct(
            $record,
            $weight,
            __('Re-weighed at the Weigh-in Station (was :previous kg).', ['previous' => number_format($previousWeight, 2)]),
            measuredOn: today(),
            remarks: $this->aiRemark($aiWeight, $weight),
        );

        $this->pendingPhotoPath = null;
        $this->lastSaved = [
            'name' => $person->rank_name,
            'weight' => $weight,
            'bmi' => $record->bmi,
            'personnel_id' => $person->id,
            'year' => $record->year,
            'automatic' => false,
            'review_record_id' => $record->needsMeasurementReview() ? $record->id : null,
        ];
        Flux::toast(variant: 'success', text: __(':name — this month\'s weight replaced with :weight kg (was :previous kg).', ['name' => $person->rank_name, 'weight' => number_format($weight, 2), 'previous' => number_format($previousWeight, 2)]));

        $this->nextPersonnel();
    }

    private function persist(MonthlyMonitoringService $monitoring, bool $automatic): void
    {
        $person = $this->personnel ?? throw ValidationException::withMessages(['personnel' => __('Scan the personnel\'s QR code or search by name first.')]);
        $this->authorize('encodeMonitoring', $person);

        $this->validate(['weight' => $this->measurementRule('weight', decimals: 2)], $this->measurementMessages());
        $measurements = $this->updateMeasurements ? $this->validatedMeasurements() : null;

        $today = today();
        $weight = (float) $this->weight;
        $aiWeight = $this->reading['weight_kg'] ?? null;

        $photoPath = null;

        if ($this->pendingPhotoPath && Storage::disk('local')->exists($this->pendingPhotoPath)) {
            $photoPath = 'weigh-in-photos/'.$person->id.'/'.$today->format('Y-m').'-'.Str::random(10).'.jpg';
            Storage::disk('local')->move($this->pendingPhotoPath, $photoPath);
        }

        try {
            $record = DB::transaction(function () use ($monitoring, $person, $today, $weight, $automatic, $aiWeight, $photoPath, $measurements): MonthlyWeightMonitoring {
                if ($measurements !== null) {
                    app(MeasurementReviewService::class)->recordAtWeighIn($person, $weight, $today, $measurements);
                }

                return $monitoring->record(
                    personnel: $person,
                    year: (int) $today->format('Y'),
                    month: (int) $today->format('n'),
                    weightKg: $weight,
                    measuredOn: $today,
                    remarks: $automatic ? __('Weigh-in Station: saved automatically from the scale photo (:source, high-confidence reading).', ['source' => $this->readerMode() === 'ai' ? 'AI reading' : 'read on device']) : $this->aiRemark($aiWeight, $weight),
                    captureMethod: 'station',
                    photoPath: $photoPath,
                    aiWeightKg: $aiWeight,
                    aiConfidence: $this->reading['confidence'] ?? null,
                );
            });
        } catch (ValidationException $exception) {
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }

            $this->pendingPhotoPath = null;

            throw $exception;
        }

        $this->pendingPhotoPath = null;
        $this->lastSaved = [
            'name' => $person->rank_name,
            'weight' => $weight,
            'bmi' => $record->bmi,
            'personnel_id' => $person->id,
            'year' => $record->year,
            'automatic' => $automatic,
            'measurements' => $measurements !== null,
            'review_record_id' => $record->needsMeasurementReview() ? $record->id : null,
        ];
        Flux::toast(variant: 'success', text: $measurements !== null
            ? __(':name — :weight kg recorded with waist, hip and wrist.', ['name' => $person->rank_name, 'weight' => number_format($weight, 2)])
            : __(':name — :weight kg recorded.', ['name' => $person->rank_name, 'weight' => number_format($weight, 2)]));

        $this->nextPersonnel();
    }

    /**
     * Latest monthly weight before the current month, used to catch implausible jumps.
     */
    private function previousRecord(): ?MonthlyWeightMonitoring
    {
        return $this->personnel?->monthlyWeights
            ->first(fn ($record) => ! ($record->year === (int) today()->format('Y') && $record->month === (int) today()->format('n')));
    }

    /**
     * Clear the station for the next personnel in line.
     */
    public function nextPersonnel(): void
    {
        $this->discardPendingPhoto();
        $this->reset('personnelId', 'photo', 'reading', 'deviceReading', 'weight', 'search', 'scanMessage', 'reviewReason',
            'updateMeasurements', 'waist_cm', 'hip_cm', 'wrist_cm', 'autoSaveHeld');
        $this->resetValidation();
        unset($this->personnel, $this->recentCaptures, $this->canUpdateMeasurements);
    }

    /**
     * Waist, hip and wrist entered at the station; at least one is required.
     *
     * @return array{waist_cm: float|null, hip_cm: float|null, wrist_cm: float|null}
     */
    private function validatedMeasurements(): array
    {
        abort_unless($this->canUpdateMeasurements, 403);

        $validated = $this->validate([
            'waist_cm' => $this->measurementRule('waist', required: false),
            'hip_cm' => $this->measurementRule('hip', required: false),
            'wrist_cm' => $this->measurementRule('wrist', required: false),
        ], $this->measurementMessages(), ['waist_cm' => 'waist', 'hip_cm' => 'hip', 'wrist_cm' => 'wrist']);

        $measurements = array_map(fn ($value) => filled($value) ? (float) $value : null, $validated);

        if (array_filter($measurements) === []) {
            throw ValidationException::withMessages(['waist_cm' => __('Enter at least one measurement, or choose "No, save weight only".')]);
        }

        return $measurements;
    }

    private function decimal(?float $value): string
    {
        return $value === null ? '' : number_format($value, 1, '.', '');
    }

    #[Computed]
    public function personnel(): ?Personnel
    {
        if (! $this->personnelId) {
            return null;
        }

        return Personnel::query()->visibleTo(Auth::user())
            ->with(['rank:id,abbreviation', 'unit:id,code,name', 'latestAssessment',
                'monthlyWeights' => fn ($query) => $query->orderByDesc('year')->orderByDesc('month')->limit(2)])
            ->find($this->personnelId);
    }

    #[Computed]
    public function thisMonthRecord(): ?MonthlyWeightMonitoring
    {
        return $this->personnel?->monthlyWeights
            ->first(fn ($record) => $record->year === (int) today()->format('Y') && $record->month === (int) today()->format('n'));
    }

    /**
     * @return Collection<int, Personnel>
     */
    #[Computed]
    public function searchResults(): Collection
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return collect();
        }

        return Personnel::query()->visibleTo(Auth::user())->active()->search($this->search)
            ->with(['rank:id,abbreviation', 'unit:id,code'])->orderBy('last_name')->limit(6)->get();
    }

    /**
     * @return Collection<int, MonthlyWeightMonitoring>
     */
    #[Computed]
    public function recentCaptures(): Collection
    {
        return MonthlyWeightMonitoring::query()
            ->where('recorded_by', Auth::id())
            ->whereDate('created_at', today())
            ->with('personnel.rank:id,abbreviation')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    private function aiRemark(?float $aiWeight, float $confirmed): string
    {
        if ($aiWeight === null) {
            return __('Recorded at the Weigh-in Station.');
        }

        return abs($aiWeight - $confirmed) < 0.005
            ? __('Weigh-in Station: weight read from the scale photo and confirmed.')
            : __('Weigh-in Station: photo read as :ai kg, corrected by staff to :weight kg.', ['ai' => number_format($aiWeight, 2), 'weight' => number_format($confirmed, 2)]);
    }

    private function discardPendingPhoto(): void
    {
        if ($this->pendingPhotoPath && str_starts_with($this->pendingPhotoPath, 'weigh-in-photos/pending/')) {
            Storage::disk('local')->delete($this->pendingPhotoPath);
        }

        $this->pendingPhotoPath = null;
    }

    public function render(MonthlyMonitoringService $monitoring): View
    {
        $settings = app(Settings::class);
        $mode = $this->readerMode();

        return view('livewire.monitoring.station', [
            'readerMode' => $mode,
            'readerEnabled' => $mode !== 'off',
            'autoSave' => $mode !== 'off' && $settings->bool('station_auto_save'),
            'weightRange' => ['min' => $settings->float('weight_min_kg'), 'max' => $settings->float('weight_max_kg'), 'decimals' => $this->scaleDecimals()],
            'deadline' => $monitoring->deadlineFor((int) today()->format('Y'), (int) today()->format('n')),
        ]);
    }
}
