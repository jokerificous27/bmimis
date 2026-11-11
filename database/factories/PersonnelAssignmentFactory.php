<?php

namespace Database\Factories;

use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonnelAssignment>
 */
class PersonnelAssignmentFactory extends Factory
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
            'effective_from' => now()->subYear()->format('Y-m-d'),
        ];
    }
}
