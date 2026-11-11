<?php

namespace App\Services\Bmi;

use App\Enums\WeightAction;
use App\Models\BmiClassification;

/**
 * The complete computed result for one set of measurements.
 */
readonly class BmiEvaluation
{
    public function __construct(
        public float $bmi,
        public ?float $waistHipRatio,
        public IdealBodyWeightRange $idealBodyWeight,
        public ?WeightAction $weightAction,
        public ?float $weightDifferenceKg,
        public ?BmiClassification $pnpClassification,
        public ?BmiClassification $whoClassification,
    ) {}

    public function requiresIntervention(): bool
    {
        return (bool) $this->pnpClassification?->requires_intervention;
    }

    public function suggestedInterventionPackageId(): ?int
    {
        return $this->pnpClassification?->intervention_package_id;
    }

    public function weightActionText(): string
    {
        return $this->weightAction?->describe($this->weightDifferenceKg) ?? '—';
    }

    /**
     * Attributes to persist on an assessment or monthly record.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'bmi' => $this->bmi,
            'waist_hip_ratio' => $this->waistHipRatio,
            'ibw_method' => $this->idealBodyWeight->method,
            'ibw_min_kg' => $this->idealBodyWeight->minKg,
            'ibw_max_kg' => $this->idealBodyWeight->maxKg,
            'weight_action' => $this->weightAction,
            'weight_difference_kg' => $this->weightDifferenceKg,
            'pnp_classification_id' => $this->pnpClassification?->id,
            'pnp_classification' => $this->pnpClassification?->label,
            'pnp_category' => $this->pnpClassification?->category,
            'who_classification_id' => $this->whoClassification?->id,
            'who_classification' => $this->whoClassification?->label,
            'who_category' => $this->whoClassification?->category,
            'requires_intervention' => $this->requiresIntervention(),
        ];
    }
}
