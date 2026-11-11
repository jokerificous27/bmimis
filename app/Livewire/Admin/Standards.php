<?php

namespace App\Livewire\Admin;

use App\Enums\ClassificationCategory;
use App\Enums\Sex;
use App\Enums\StandardType;
use App\Models\BmiClassification;
use App\Models\BmiStandard;
use App\Models\InterventionPackage;
use App\Services\Bmi\BmiClassifier;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Versioned WHO and PNP BMI classification standards. Historical assessments keep a snapshot of the
 * classification that applied when they were recorded, so editing a standard never rewrites history.
 */
#[Title('BMI Standards')]
class Standards extends Component
{
    #[Url(as: 'standard')]
    public ?int $standardId = null;

    public string $name = '';

    public string $reference = '';

    public string $description = '';

    public string $effective_from = '';

    public string $effective_to = '';

    public bool $is_active = true;

    public bool $is_provisional = false;

    /**
     * @var list<array<string, mixed>>
     */
    public array $bands = [];

    public function mount(): void
    {
        $this->standardId ??= BmiStandard::query()->effectiveOn(StandardType::Pnp, today())->value('id') ?? BmiStandard::query()->value('id');

        if ($this->standardId) {
            $this->select($this->standardId);
        }
    }

    public function select(int $id): void
    {
        $standard = BmiStandard::with('classifications')->findOrFail($id);
        $this->resetValidation();

        $this->standardId = $standard->id;
        $this->name = $standard->name;
        $this->reference = (string) $standard->reference;
        $this->description = (string) $standard->description;
        $this->effective_from = $standard->effective_from->format('Y-m-d');
        $this->effective_to = (string) $standard->effective_to?->format('Y-m-d');
        $this->is_active = $standard->is_active;
        $this->is_provisional = $standard->is_provisional;
        $this->bands = $standard->classifications->map(fn (BmiClassification $band) => [
            'id' => $band->id,
            'label' => $band->label,
            'category' => $band->category->value,
            'min_bmi' => $band->min_bmi === null ? '' : (string) $band->min_bmi,
            'max_bmi' => $band->max_bmi === null ? '' : (string) $band->max_bmi,
            'min_age' => $band->min_age === null ? '' : (string) $band->min_age,
            'max_age' => $band->max_age === null ? '' : (string) $band->max_age,
            'sex' => $band->sex?->value ?? '',
            'requires_intervention' => $band->requires_intervention,
            'intervention_package_id' => (string) $band->intervention_package_id,
            'color' => $band->color,
        ])->all();
    }

    /**
     * Start a new version of a standard from the one currently in force.
     */
    public function newVersion(string $type): void
    {
        $type = StandardType::from($type);
        $current = BmiStandard::query()->effectiveOn($type, today())->with('classifications')->first();

        $standard = DB::transaction(function () use ($type, $current) {
            $standard = BmiStandard::create([
                'type' => $type,
                'name' => $type === StandardType::Pnp ? 'PNP Acceptable BMI (new version)' : 'WHO Adult BMI Classification (new version)',
                'reference' => null,
                'effective_from' => today()->addDay(),
                'is_active' => false,
                'is_provisional' => $type === StandardType::Pnp,
            ]);

            foreach ($current?->classifications ?? [] as $band) {
                $standard->classifications()->create($band->only(['label', 'category', 'min_bmi', 'max_bmi', 'min_age', 'max_age', 'sex', 'requires_intervention', 'intervention_package_id', 'color', 'sort_order']));
            }

            return $standard;
        });

        $this->select($standard->id);
        Flux::toast(text: __('Draft version created (inactive). Review the thresholds, set the effective date and activate it.'));
    }

    public function addBand(): void
    {
        $this->bands[] = ['id' => null, 'label' => '', 'category' => ClassificationCategory::Normal->value, 'min_bmi' => '', 'max_bmi' => '', 'min_age' => '', 'max_age' => '', 'sex' => '', 'requires_intervention' => false, 'intervention_package_id' => '', 'color' => 'zinc'];
    }

    public function removeBand(int $index): void
    {
        unset($this->bands[$index]);
        $this->bands = array_values($this->bands);
    }

    public function save(): void
    {
        $standard = BmiStandard::findOrFail($this->standardId);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
            'is_provisional' => ['boolean'],
            'bands' => ['required', 'array', 'min:1'],
            'bands.*.label' => ['required', 'string', 'max:100'],
            'bands.*.category' => ['required', Rule::enum(ClassificationCategory::class)],
            'bands.*.min_bmi' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bands.*.max_bmi' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bands.*.min_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'bands.*.max_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'bands.*.sex' => ['nullable', Rule::enum(Sex::class)],
            'bands.*.requires_intervention' => ['boolean'],
            'bands.*.intervention_package_id' => ['nullable', 'exists:intervention_packages,id'],
            'bands.*.color' => ['required', Rule::in(BmiClassification::COLORS)],
        ], [], ['bands.*.label' => 'label', 'bands.*.min_bmi' => 'minimum BMI', 'bands.*.max_bmi' => 'maximum BMI']);

        $this->assertConsistentBands();

        DB::transaction(function () use ($standard): void {
            $standard->update([
                'name' => $this->name,
                'reference' => $this->reference ?: null,
                'description' => $this->description ?: null,
                'effective_from' => $this->effective_from,
                'effective_to' => $this->effective_to ?: null,
                'is_active' => $this->is_active,
                'is_provisional' => $this->is_provisional,
            ]);

            $keep = [];

            foreach (array_values($this->bands) as $order => $band) {
                $attributes = [
                    'label' => $band['label'],
                    'category' => $band['category'],
                    'min_bmi' => $band['min_bmi'] === '' ? null : $band['min_bmi'],
                    'max_bmi' => $band['max_bmi'] === '' ? null : $band['max_bmi'],
                    'min_age' => $band['min_age'] === '' ? null : $band['min_age'],
                    'max_age' => $band['max_age'] === '' ? null : $band['max_age'],
                    'sex' => $band['sex'] ?: null,
                    'requires_intervention' => (bool) $band['requires_intervention'],
                    'intervention_package_id' => $band['intervention_package_id'] ?: null,
                    'color' => $band['color'],
                    'sort_order' => $order + 1,
                ];

                $model = $band['id'] ? $standard->classifications()->findOrFail($band['id']) : $standard->classifications()->make();
                $model->fill($attributes)->save();
                $keep[] = $model->id;
            }

            $standard->classifications()->whereNotIn('id', $keep)->get()->each->delete();
        });

        app(BmiClassifier::class)->flush();
        $this->select($standard->id);

        Flux::toast(variant: 'success', text: __('Standard saved. New assessments will use these thresholds; existing results are unchanged.'));
    }

    /**
     * Each band needs a valid range, and bands that apply to the same people may not overlap.
     */
    private function assertConsistentBands(): void
    {
        $errors = [];
        $bands = array_values($this->bands);

        foreach ($bands as $i => $band) {
            if ($band['min_bmi'] !== '' && $band['max_bmi'] !== '' && (float) $band['min_bmi'] >= (float) $band['max_bmi']) {
                $errors["bands.{$i}.max_bmi"] = __('Maximum must be greater than minimum.');
            }

            if ($band['min_age'] !== '' && $band['max_age'] !== '' && (int) $band['min_age'] > (int) $band['max_age']) {
                $errors["bands.{$i}.max_age"] = __('Maximum age must not be below minimum age.');
            }

            foreach ($bands as $j => $other) {
                if ($j <= $i || ! $this->sameAudience($band, $other)) {
                    continue;
                }

                $aMin = $band['min_bmi'] === '' ? -INF : (float) $band['min_bmi'];
                $aMax = $band['max_bmi'] === '' ? INF : (float) $band['max_bmi'];
                $bMin = $other['min_bmi'] === '' ? -INF : (float) $other['min_bmi'];
                $bMax = $other['max_bmi'] === '' ? INF : (float) $other['max_bmi'];

                if ($aMin < $bMax && $bMin < $aMax) {
                    $errors["bands.{$j}.min_bmi"] = __('Overlaps with ":label" for the same age/sex group.', ['label' => $band['label']]);
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function sameAudience(array $a, array $b): bool
    {
        if ($a['sex'] !== '' && $b['sex'] !== '' && $a['sex'] !== $b['sex']) {
            return false;
        }

        if (($a['sex'] === '') !== ($b['sex'] === '')) {
            return false;
        }

        $aMinAge = $a['min_age'] === '' ? 0 : (int) $a['min_age'];
        $aMaxAge = $a['max_age'] === '' ? 999 : (int) $a['max_age'];
        $bMinAge = $b['min_age'] === '' ? 0 : (int) $b['min_age'];
        $bMaxAge = $b['max_age'] === '' ? 999 : (int) $b['max_age'];

        $aGeneral = $a['min_age'] === '' && $a['max_age'] === '';
        $bGeneral = $b['min_age'] === '' && $b['max_age'] === '';

        return $aGeneral === $bGeneral && $aMinAge <= $bMaxAge && $bMinAge <= $aMaxAge;
    }

    public function render(): View
    {
        return view('livewire.admin.standards', [
            'standards' => BmiStandard::query()->withCount('classifications')->orderBy('type')->orderByDesc('effective_from')->get()->groupBy(fn ($s) => $s->type->value),
            'current' => [
                'who' => BmiStandard::query()->effectiveOn(StandardType::Who, today())->value('id'),
                'pnp' => BmiStandard::query()->effectiveOn(StandardType::Pnp, today())->value('id'),
            ],
            'selected' => $this->standardId ? BmiStandard::find($this->standardId) : null,
            'categories' => ClassificationCategory::options(),
            'sexes' => Sex::options(),
            'packages' => InterventionPackage::query()->orderBy('code')->get(['id', 'code', 'name']),
            'colors' => BmiClassification::COLORS,
        ]);
    }
}
