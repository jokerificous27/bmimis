<?php

namespace Database\Factories;

use App\Enums\InterventionStatus;
use App\Models\InterventionPackage;
use App\Models\Personnel;
use App\Models\PersonnelIntervention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonnelIntervention>
 */
class PersonnelInterventionFactory extends Factory
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
            'intervention_package_id' => InterventionPackage::factory(),
            'assigned_on' => now()->subMonth()->format('Y-m-d'),
            'next_follow_up_on' => now()->addWeek()->format('Y-m-d'),
            'status' => InterventionStatus::Ongoing,
        ];
    }
}
