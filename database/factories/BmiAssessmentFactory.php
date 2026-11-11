<?php

namespace Database\Factories;

use App\Enums\AssessmentStatus;
use App\Enums\ClassificationCategory;
use App\Enums\IbwMethod;
use App\Enums\WeightAction;
use App\Models\BmiAssessment;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BmiAssessment>
 */
class BmiAssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $height = fake()->numberBetween(155, 185);
        $weight = fake()->randomFloat(2, 52, 95);
        $bmi = round($weight / (($height / 100) ** 2), 1);
        $category = $bmi < 18.5 ? ClassificationCategory::Below : ($bmi < 25 ? ClassificationCategory::Normal : ClassificationCategory::Above);

        return [
            'personnel_id' => Personnel::factory(),
            'assessed_on' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'height_cm' => $height,
            'weight_kg' => $weight,
            'waist_cm' => fake()->numberBetween(70, 105),
            'hip_cm' => fake()->numberBetween(85, 110),
            'wrist_cm' => fake()->numberBetween(14, 19),
            'bmi' => $bmi,
            'ibw_method' => IbwMethod::BmiRange,
            'ibw_min_kg' => round(18.5 * ($height / 100) ** 2, 1),
            'ibw_max_kg' => round(25 * ($height / 100) ** 2, 1),
            'weight_action' => WeightAction::Maintain,
            'weight_difference_kg' => 0,
            'pnp_classification' => $category->label(),
            'pnp_category' => $category,
            'who_classification' => $category->label(),
            'who_category' => $category,
            'requires_intervention' => $category !== ClassificationCategory::Normal,
            'status' => AssessmentStatus::Submitted,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => AssessmentStatus::Draft]);
    }

    public function certified(): static
    {
        return $this->state(fn () => ['status' => AssessmentStatus::Certified, 'certified_at' => now()]);
    }
}
