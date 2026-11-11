<?php

namespace Database\Factories;

use App\Models\InterventionFollowUp;
use App\Models\PersonnelIntervention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterventionFollowUp>
 */
class InterventionFollowUpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'personnel_intervention_id' => PersonnelIntervention::factory(),
            'follow_up_on' => now()->format('Y-m-d'),
            'weight_kg' => fake()->randomFloat(2, 55, 90),
            'notes' => null,
        ];
    }
}
