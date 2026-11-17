<?php

namespace App\Services\Bmi;

use App\Enums\ClassificationCategory;
use App\Enums\FrameSize;
use App\Enums\PnpAcceptance;
use App\Enums\WeightAction;
use App\Models\BmiClassification;

/**
 * The complete computed result for one set of measurements.
 */
readonly class BmiEvaluation
{
    /**
     * @param  BmiClassification|null  $pnpClassification  the PNP band of the BMI for the age, before any exemption
     * @param  PnpAcceptance|null  $pnpAcceptance  set when a BMI above the acceptable range is accepted by large body frame or waist circumference
     */
    public function __construct(
        public float $bmi,
        public ?float $waistHipRatio,
        public IdealBodyWeightRange $idealBodyWeight,
        public ?WeightAction $weightAction,
        public ?float $weightDifferenceKg,
        public ?BmiClassification $pnpClassification,
        public ?BmiClassification $whoClassification,
        public ?float $heightWristRatio = null,
        public ?FrameSize $frameSize = null,
        public ?PnpAcceptance $pnpAcceptance = null,
    ) {}

    /**
     * The PNP classification as written on records: the band, or the exemption that applies.
     */
    public function pnpLabel(): ?string
    {
        return $this->pnpAcceptance?->label() ?? $this->pnpClassification?->label;
    }

    public function pnpCategory(): ?ClassificationCategory
    {
        return $this->pnpAcceptance ? ClassificationCategory::Normal : $this->pnpClassification?->category;
    }

    public function requiresIntervention(): bool
    {
        return $this->pnpAcceptance === null && (bool) $this->pnpClassification?->requires_intervention;
    }

    public function suggestedInterventionPackageId(): ?int
    {
        return $this->pnpAcceptance === null ? $this->pnpClassification?->intervention_package_id : null;
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
            'height_wrist_ratio' => $this->heightWristRatio,
            'frame_size' => $this->frameSize,
            'ibw_method' => $this->idealBodyWeight->method,
            'ibw_min_kg' => $this->idealBodyWeight->minKg,
            'ibw_max_kg' => $this->idealBodyWeight->maxKg,
            'weight_action' => $this->weightAction,
            'weight_difference_kg' => $this->weightDifferenceKg,
            'pnp_classification_id' => $this->pnpClassification?->id,
            'pnp_classification' => $this->pnpLabel(),
            'pnp_category' => $this->pnpCategory(),
            'pnp_acceptance' => $this->pnpAcceptance,
            'who_classification_id' => $this->whoClassification?->id,
            'who_classification' => $this->whoClassification?->label,
            'who_category' => $this->whoClassification?->category,
            'requires_intervention' => $this->requiresIntervention(),
        ];
    }
}
