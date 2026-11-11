<?php

namespace App\Services\WeighIn;

/**
 * Result of reading a weighing-scale display from a photo. Always confirmed by Health Service staff before saving.
 */
readonly class ScaleReading
{
    public function __construct(
        public ?float $weightKg,
        public string $confidence,
        public ?string $displayedText = null,
        public ?string $note = null,
    ) {}

    public static function unavailable(string $note): self
    {
        return new self(null, 'none', null, $note);
    }

    public function hasWeight(): bool
    {
        return $this->weightKg !== null;
    }

    /**
     * @return array{weight_kg: float|null, confidence: string, displayed_text: string|null, note: string|null}
     */
    public function toArray(): array
    {
        return [
            'weight_kg' => $this->weightKg,
            'confidence' => $this->confidence,
            'displayed_text' => $this->displayedText,
            'note' => $this->note,
        ];
    }
}
