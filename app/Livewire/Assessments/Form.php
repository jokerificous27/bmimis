<?php

namespace App\Livewire\Assessments;

use App\Enums\IbwMethod;
use App\Enums\Role;
use App\Livewire\Concerns\ValidatesMeasurements;
use App\Livewire\Concerns\WithLookups;
use App\Models\BmiAssessment;
use App\Models\Personnel;
use App\Models\User;
use App\Services\AssessmentService;
use App\Services\Bmi\BmiEvaluation;
use App\Services\Bmi\BmiEvaluator;
use App\Services\MonthlyMonitoringService;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

#[Title('BMI Assessment')]
class Form extends Component
{
    use ValidatesMeasurements, WithFileUploads, WithLookups;

    public ?BmiAssessment $assessment = null;

    public ?int $personnelId = null;

    public string $personnelSearch = '';

    public string $assessed_on = '';

    public string $height_cm = '';

    public string $weight_kg = '';

    public string $waist_cm = '';

    public string $hip_cm = '';

    public string $wrist_cm = '';

    public string $ibw_min_kg = '';

    public string $ibw_max_kg = '';

    public string $intervention_package_id = '';

    public string $unit_representative_name = '';

    public string $unit_representative_designation = '';

    public string $health_service_representative_name = '';

    public string $health_service_representative_designation = '';

    public string $remarks = '';

    public bool $record_monthly = true;

    public string $correction_reason = '';

    public bool $confirm_replace = false;

    /** @var TemporaryUploadedFile|null */
    public $photo_front = null;

    /** @var TemporaryUploadedFile|null */
    public $photo_right = null;

    /** @var TemporaryUploadedFile|null */
    public $photo_left = null;

    public function mount(?BmiAssessment $assessment = null): void
    {
        if ($assessment?->exists) {
            $this->authorize('update', $assessment);
            $this->assessment = $assessment;
            $this->personnelId = $assessment->personnel_id;
            $this->assessed_on = $assessment->assessed_on->format('Y-m-d');
            $this->height_cm = $this->decimal($assessment->height_cm, 1);
            $this->weight_kg = $this->decimal($assessment->weight_kg, 2);
            $this->waist_cm = $this->decimal($assessment->waist_cm, 1);
            $this->hip_cm = $this->decimal($assessment->hip_cm, 1);
            $this->wrist_cm = $this->decimal($assessment->wrist_cm, 1);
            $this->ibw_min_kg = $this->decimal($assessment->ibw_method === IbwMethod::Manual ? $assessment->ibw_min_kg : null, 1);
            $this->ibw_max_kg = $this->decimal($assessment->ibw_method === IbwMethod::Manual ? $assessment->ibw_max_kg : null, 1);
            $this->intervention_package_id = (string) $assessment->intervention_package_id;
            $this->remarks = (string) $assessment->remarks;
            $this->record_monthly = false;

            foreach (['unit_representative_name', 'unit_representative_designation', 'health_service_representative_name', 'health_service_representative_designation'] as $field) {
                $this->{$field} = (string) $assessment->{$field};
            }

            return;
        }

        $this->authorize('create', BmiAssessment::class);
        $this->assessment = null;
        $this->assessed_on = today()->format('Y-m-d');

        if ($requested = request()->integer('personnel')) {
            $this->selectPersonnel($requested);
        }
    }

    private function decimal(?float $value, int $decimals): string
    {
        return $value === null ? '' : number_format($value, $decimals, '.', '');
    }

    #[Computed]
    public function selectedPersonnel(): ?Personnel
    {
        return $this->personnelId
            ? Personnel::query()->visibleTo(Auth::user())->with(['rank', 'unit'])->find($this->personnelId)
            : null;
    }

    /**
     * @return Collection<int, Personnel>
     */
    #[Computed]
    public function personnelResults(): Collection
    {
        if (mb_strlen(trim($this->personnelSearch)) < 2) {
            return collect();
        }

        return Personnel::query()->visibleTo(Auth::user())->active()->search($this->personnelSearch)
            ->with(['rank:id,abbreviation', 'unit:id,code'])
            ->orderBy('last_name')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function ibwIsManual(): bool
    {
        return app(BmiEvaluator::class)->ibwMethod() === IbwMethod::Manual;
    }

    /**
     * Live computation shown while encoding. Returns null until height and weight are plausible.
     */
    #[Computed]
    public function preview(): ?BmiEvaluation
    {
        $person = $this->selectedPersonnel;
        $settings = app(Settings::class);

        if (! $person || ! is_numeric($this->height_cm) || ! is_numeric($this->weight_kg)) {
            return null;
        }

        $height = (float) $this->height_cm;
        $weight = (float) $this->weight_kg;

        if ($height < $settings->float('height_min_cm') || $height > $settings->float('height_max_cm') || $weight < $settings->float('weight_min_kg') || $weight > $settings->float('weight_max_kg')) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($this->assessed_on ?: today());
        } catch (Throwable) {
            $date = CarbonImmutable::today();
        }

        return app(AssessmentService::class)->evaluate(
            personnel: $person,
            heightCm: $height,
            weightKg: $weight,
            date: $date,
            waistCm: is_numeric($this->waist_cm) ? (float) $this->waist_cm : null,
            hipCm: is_numeric($this->hip_cm) ? (float) $this->hip_cm : null,
            manualIbw: ['min' => is_numeric($this->ibw_min_kg) ? (float) $this->ibw_min_kg : null, 'max' => is_numeric($this->ibw_max_kg) ? (float) $this->ibw_max_kg : null],
        );
    }

    /**
     * Height detected as metres (e.g. 1.69) is converted to centimetres.
     */
    public function updatedHeightCm(): void
    {
        if (is_numeric($this->height_cm) && (float) $this->height_cm > 0.5 && (float) $this->height_cm < 2.5) {
            $this->height_cm = number_format((float) $this->height_cm * 100, 1, '.', '');
            Flux::toast(text: __('Height looked like metres and was converted to :cm cm.', ['cm' => $this->height_cm]));
        }
    }

    public function selectPersonnel(int $personnelId): void
    {
        $person = Personnel::query()->visibleTo(Auth::user())->with('unit')->findOrFail($personnelId);
        $this->authorize('encodeAssessment', $person);

        $this->personnelId = $person->id;
        $this->personnelSearch = '';
        unset($this->selectedPersonnel);

        $representative = User::query()->where('role', Role::UnitRepresentative->value)->where('unit_id', $person->unit_id)->where('is_active', true)->first();
        $this->unit_representative_name = (string) ($representative?->name ?? '');
        $this->unit_representative_designation = (string) ($representative?->designation ?? '');

        $settings = app(Settings::class);
        $user = Auth::user();
        $this->health_service_representative_name = (string) ($settings->get('default_hs_representative_name') ?: ($user->isStaff() ? $user->name : ''));
        $this->health_service_representative_designation = (string) ($settings->get('default_hs_representative_designation') ?: ($user->isStaff() ? $user->designation : ''));

        $latest = $person->latestAssessment;
        if ($latest && $this->height_cm === '') {
            $this->height_cm = $this->decimal($latest->height_cm, 1);
        }

        $date = CarbonImmutable::parse($this->assessed_on ?: today());
        $this->record_monthly = ! app(MonthlyMonitoringService::class)->exists($person, $date->year, $date->month);
    }

    public function clearPersonnel(): void
    {
        abort_if($this->assessment !== null, 403);

        $this->personnelId = null;
        unset($this->selectedPersonnel);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $photo = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

        return [
            'personnelId' => ['required', 'integer'],
            'assessed_on' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'height_cm' => $this->measurementRule('height'),
            'weight_kg' => $this->measurementRule('weight', decimals: 2),
            'waist_cm' => $this->measurementRule('waist', required: false),
            'hip_cm' => $this->measurementRule('hip', required: false),
            'wrist_cm' => $this->measurementRule('wrist', required: false),
            'ibw_min_kg' => [$this->ibwIsManual ? 'required' : 'nullable', 'numeric', 'min:20', 'max:200'],
            'ibw_max_kg' => [$this->ibwIsManual ? 'required' : 'nullable', 'numeric', 'gte:ibw_min_kg', 'max:250'],
            'intervention_package_id' => ['nullable', Rule::exists('intervention_packages', 'id')->whereNull('deleted_at')],
            'unit_representative_name' => ['nullable', 'string', 'max:255'],
            'unit_representative_designation' => ['nullable', 'string', 'max:255'],
            'health_service_representative_name' => ['nullable', 'string', 'max:255'],
            'health_service_representative_designation' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'correction_reason' => [$this->assessment?->isOfficial() ? 'required' : 'nullable', 'string', 'max:500'],
            'photo_front' => $photo,
            'photo_right' => $photo,
            'photo_left' => $photo,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return array_merge($this->measurementMessages(), [
            'personnelId.required' => __('Select the personnel being assessed.'),
            'correction_reason.required' => __('State the reason for correcting this assessment.'),
        ]);
    }

    public function saveDraft(AssessmentService $service): void
    {
        $this->persist($service, submit: false);
    }

    /**
     * Validate and show the full result preview before submitting.
     */
    public function review(AssessmentService $service): void
    {
        $this->validate();

        $person = $this->selectedPersonnel ?? abort(404);
        $existing = $service->findSameDayAssessment($person, CarbonImmutable::parse($this->assessed_on), $this->assessment?->id);
        $this->confirm_replace = false;

        Flux::modal('review-assessment')->show();

        if ($existing && ! $this->assessment) {
            $this->addError('assessed_on', __('An assessment dated :date already exists (:status). Submitting will supersede it; the previous record is kept in the history.', [
                'date' => $existing->assessed_on->format('F j, Y'),
                'status' => $existing->status->label(),
            ]));
        }
    }

    public function submit(AssessmentService $service): void
    {
        $this->persist($service, submit: true);
    }

    private function persist(AssessmentService $service, bool $submit): void
    {
        $validated = $this->validate();
        $person = $this->selectedPersonnel ?? abort(404);
        $this->authorize('encodeAssessment', $person);

        $data = collect($validated)->only([
            'assessed_on', 'height_cm', 'weight_kg', 'waist_cm', 'hip_cm', 'wrist_cm', 'intervention_package_id', 'ibw_min_kg', 'ibw_max_kg',
            'unit_representative_name', 'unit_representative_designation', 'health_service_representative_name', 'health_service_representative_designation', 'remarks',
        ])->map(fn ($value) => $value === '' ? null : $value)->all();

        if ($this->assessment) {
            $this->authorize('update', $this->assessment);
            $assessment = $service->update($this->assessment, $data, $this->correction_reason ?: null, $submit, $this->record_monthly);
        } else {
            $existing = $service->findSameDayAssessment($person, CarbonImmutable::parse($this->assessed_on));

            if ($existing && ! $this->confirm_replace) {
                $this->addError('assessed_on', __('An assessment for this date already exists. Tick the confirmation to supersede it.'));
                Flux::modal('review-assessment')->show();

                return;
            }

            $assessment = $service->create($person, $data, $submit, $this->record_monthly, confirmReplace: $this->confirm_replace);
        }

        $this->storePhotos($assessment);

        Flux::toast(variant: 'success', text: match (true) {
            $this->assessment?->isOfficial() ?? false => __('Correction saved and logged in the audit trail.'),
            $submit => __('Assessment submitted for certification.'),
            default => __('Draft saved.'),
        });

        $this->redirectRoute('assessments.show', $assessment, navigate: true);
    }

    private function storePhotos(BmiAssessment $assessment): void
    {
        $paths = [];

        foreach (['front', 'right', 'left'] as $view) {
            $upload = $this->{'photo_'.$view};

            if ($upload instanceof TemporaryUploadedFile) {
                $paths['photo_'.$view.'_path'] = $upload->storeAs(
                    'assessment-photos/'.$assessment->personnel_id,
                    $assessment->id.'-'.$view.'-'.Str::random(12).'.'.$upload->guessExtension(),
                    'local',
                );
            }
        }

        if ($paths !== []) {
            $assessment->auditRemarks = 'Photos uploaded';
            $assessment->forceFill($paths)->save();
        }
    }

    public function render(): View
    {
        $isCorrection = (bool) $this->assessment?->isOfficial();

        return view('livewire.assessments.form', [
            'isCorrection' => $isCorrection,
            'ibwMethod' => app(BmiEvaluator::class)->ibwMethod(),
        ])->title($this->assessment ? ($isCorrection ? __('Correct assessment') : __('Edit draft assessment')) : __('New BMI assessment'));
    }
}
