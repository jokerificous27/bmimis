<?php

namespace Database\Factories;

use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyWeightMonitoring>
 */
class MonthlyWeightMonitoringFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'personnel_id' => Personnel::factory(),
            'year' => (int) now()->format('Y'),
            'month' => 1,
            'weight_kg' => fake()->randomFloat(2, 55, 90),
            'height_cm' => 170,
            'bmi' => 22.0,
        ];
    }
}
