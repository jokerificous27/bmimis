<?php

namespace App\Services\Bmi;

use App\Enums\IbwMethod;
use App\Enums\Sex;
use App\Enums\StandardType;
use App\Support\Settings;
use Carbon\CarbonInterface;

/**
 * Computes BMI, ideal body weight, weight to lose/gain and PNP/WHO classifications for a set of measurements.
 * This is the single entry point used by assessment encoding, monthly monitoring and live previews.
 */
class BmiEvaluator
{
    public function __construct(
        private Settings $settings,
        private BmiClassifier $classifier,
    ) {}

    public function calculator(): BmiCalculator
    {
        return new BmiCalculator(
            precision: $this->settings->int('bmi_precision'),
            ibwBmiLower: $this->settings->float('ibw_bmi_lower'),
            ibwBmiUpper: $this->settings->float('ibw_bmi_upper'),
            ibwTolerancePercent: $this->settings->float('ibw_tolerance_percent'),
        );
    }

    public function ibwMethod(): IbwMethod
    {
        return IbwMethod::tryFrom((string) $this->settings->get('ibw_method')) ?? IbwMethod::BmiRange;
    }

    /**
     * @param  array{min: float|null, max: float|null}|null  $manualIbw  official IBW range when the method is "manual"
     */
    public function evaluate(
        float $heightCm,
        float $weightKg,
        CarbonInterface $date,
        ?Sex $sex = null,
        ?int $age = null,
        ?float $waistCm = null,
        ?float $hipCm = null,
        ?array $manualIbw = null,
    ): BmiEvaluation {
        $calculator = $this->calculator();
        $method = $this->ibwMethod();

        $bmi = $calculator->bmi($weightKg, $heightCm);

        $ibw = $method === IbwMethod::Manual
            ? new IdealBodyWeightRange($manualIbw['min'] ?? null, $manualIbw['max'] ?? null, IbwMethod::Manual)
            : $calculator->idealBodyWeight($heightCm, $sex, $method);

        $adjustment = $calculator->weightAdjustment($weightKg, $ibw->minKg, $ibw->maxKg);

        return new BmiEvaluation(
            bmi: $bmi,
            waistHipRatio: $calculator->waistHipRatio($waistCm, $hipCm),
            idealBodyWeight: $ibw,
            weightAction: $adjustment['action'],
            weightDifferenceKg: $adjustment['difference'],
            pnpClassification: $this->classifier->classify($bmi, StandardType::Pnp, $date, $age, $sex),
            whoClassification: $this->classifier->classify($bmi, StandardType::Who, $date, $age, $sex),
        );
    }
}
