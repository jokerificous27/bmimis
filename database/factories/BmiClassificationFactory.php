<?php

namespace Database\Factories;

use App\Enums\ClassificationCategory;
use App\Models\BmiClassification;
use App\Models\BmiStandard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BmiClassification>
 */
class BmiClassificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bmi_standard_id' => BmiStandard::factory(),
            'label' => 'Normal',
            'category' => ClassificationCategory::Normal,
            'min_bmi' => 18.5,
            'max_bmi' => 25,
            'requires_intervention' => false,
            'color' => 'green',
            'sort_order' => 0,
        ];
    }
}
