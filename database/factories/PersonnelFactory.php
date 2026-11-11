<?php

namespace Database\Factories;

use App\Enums\PersonnelStatus;
use App\Enums\Sex;
use App\Models\Personnel;
use App\Models\Rank;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Personnel>
 */
class PersonnelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sex = fake()->randomElement(Sex::cases());

        return [
            'employee_number' => fake()->unique()->numerify('PNP-######'),
            'rank_id' => Rank::factory(),
            'unit_id' => Unit::factory(),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName($sex === Sex::Male ? 'male' : 'female'),
            'middle_name' => fake()->lastName(),
            'suffix' => null,
            'sex' => $sex,
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-22 years')->format('Y-m-d'),
            'position' => fake()->randomElement(['Admin PNCO', 'Investigator', 'IT Officer', 'Patrol Officer', 'Records PNCO', 'Finance PNCO']),
            'contact_number' => fake()->numerify('09#########'),
            'email' => null,
            'status' => PersonnelStatus::Active,
        ];
    }

    public function forUser(?User $user = null): static
    {
        return $this->state(fn () => ['user_id' => $user?->id ?? User::factory()]);
    }

    public function inUnit(Unit $unit): static
    {
        return $this->state(fn () => ['unit_id' => $unit->id]);
    }
}
