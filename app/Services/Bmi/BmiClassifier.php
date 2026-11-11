<?php

namespace App\Services\Bmi;

use App\Enums\Sex;
use App\Enums\StandardType;
use App\Models\BmiClassification;
use App\Models\BmiStandard;
use Carbon\CarbonInterface;

/**
 * Resolves a BMI value against the WHO or PNP standard that was in force on a given date.
 */
class BmiClassifier
{
    /**
     * @var array<string, BmiStandard|null>
     */
    private array $standards = [];

    public function standardFor(StandardType $type, CarbonInterface $date): ?BmiStandard
    {
        $key = $type->value.'|'.$date->format('Y-m-d');

        if (! array_key_exists($key, $this->standards)) {
            $this->standards[$key] = BmiStandard::query()
                ->effectiveOn($type, $date)
                ->with('classifications.interventionPackage')
                ->first();
        }

        return $this->standards[$key];
    }

    public function classify(float $bmi, StandardType $type, CarbonInterface $date, ?int $age = null, ?Sex $sex = null): ?BmiClassification
    {
        $standard = $this->standardFor($type, $date);

        if ($standard === null) {
            return null;
        }

        $candidates = $standard->classifications->filter(fn (BmiClassification $classification) => $classification->matches($bmi, $age, $sex));

        // Prefer the most specific band (age/sex specific rows win over general rows).
        return $candidates->sortByDesc(fn (BmiClassification $classification) => ($classification->sex ? 2 : 0) + ($classification->min_age !== null || $classification->max_age !== null ? 1 : 0))
            ->first();
    }

    public function flush(): void
    {
        $this->standards = [];
    }
}
