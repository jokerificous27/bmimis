<?php

namespace Database\Factories;

use App\Models\InterventionPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterventionPackage>
 */
class InterventionPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'name' => 'Sample intervention package',
            'description' => 'Fictitious package for testing.',
            'follow_up_interval_days' => 30,
            'is_active' => true,
        ];
    }
}
