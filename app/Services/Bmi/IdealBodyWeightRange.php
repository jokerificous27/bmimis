<?php

namespace App\Services\Bmi;

use App\Enums\IbwMethod;

readonly class IdealBodyWeightRange
{
    public function __construct(
        public ?float $minKg,
        public ?float $maxKg,
        public IbwMethod $method,
    ) {}

    public function isKnown(): bool
    {
        return $this->minKg !== null && $this->maxKg !== null;
    }

    public function label(): string
    {
        return $this->isKnown()
            ? number_format((float) $this->minKg, 1).' kg – '.number_format((float) $this->maxKg, 1).' kg'
            : '—';
    }
}
