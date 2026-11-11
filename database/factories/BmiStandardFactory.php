<?php

namespace Database\Factories;

use App\Enums\StandardType;
use App\Models\BmiStandard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BmiStandard>
 */
class BmiStandardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => StandardType::Who,
            'name' => 'WHO Adult BMI Classification',
            'reference' => null,
            'effective_from' => '2000-01-01',
            'effective_to' => null,
            'is_active' => true,
            'is_provisional' => false,
        ];
    }

    public function pnp(): static
    {
        return $this->state(fn () => ['type' => StandardType::Pnp, 'name' => 'PNP Acceptable BMI']);
    }
}
