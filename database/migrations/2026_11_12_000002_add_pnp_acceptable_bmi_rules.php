<?php

use App\Enums\ClassificationCategory;
use App\Enums\StandardType;
use App\Models\BmiStandard;
use App\Models\InterventionPackage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STANDARD_NAME = 'PNP Acceptable BMI Classification (PNP HS Recommended)';

    private const EFFECTIVE_FROM = '2019-11-28';

    /**
     * Columns for the large-body-frame and waist-circumference rules, and the official PNP Health
     * Service BMI bands per age group in place of the provisional standard.
     */
    public function up(): void
    {
        Schema::table('bmi_assessments', function (Blueprint $table) {
            $table->boolean('bulging_abdomen')->nullable()->after('wrist_cm');
            $table->decimal('height_wrist_ratio', 4, 1)->nullable()->after('waist_hip_ratio');
            $table->string('frame_size', 10)->nullable()->after('height_wrist_ratio');
            $table->string('pnp_acceptance', 20)->nullable()->after('pnp_category');
        });

        $this->seedOfficialStandard();
    }

    public function down(): void
    {
        $standard = BmiStandard::query()->where('type', StandardType::Pnp->value)->where('name', self::STANDARD_NAME)->first();

        if ($standard) {
            BmiStandard::query()->where('type', StandardType::Pnp->value)->where('is_provisional', true)
                ->whereDate('effective_to', '2019-11-27')->update(['effective_to' => null]);
            $standard->classifications()->delete();
            $standard->forceDelete();
        }

        Schema::table('bmi_assessments', function (Blueprint $table) {
            $table->dropColumn(['bulging_abdomen', 'height_wrist_ratio', 'frame_size', 'pnp_acceptance']);
        });
    }

    /**
     * "PNP Acceptable BMI Classification (PNP HS Recommended)", BMI Team MSD-HS, November 28, 2019.
     * Skipped when an official (non-provisional) PNP standard was already encoded by an administrator.
     * Results already saved are snapshots and are not changed.
     */
    private function seedOfficialStandard(): void
    {
        if (BmiStandard::query()->where('type', StandardType::Pnp->value)->where('is_provisional', false)->exists()) {
            return;
        }

        // The provisional standard stops the day before the official chart takes effect.
        BmiStandard::query()->where('type', StandardType::Pnp->value)->where('is_provisional', true)->whereNull('effective_to')
            ->update(['effective_to' => '2019-11-27']);

        $standard = BmiStandard::create([
            'type' => StandardType::Pnp,
            'name' => self::STANDARD_NAME,
            'reference' => 'PNP Health Service, BMI Team MSD-HS — chart dated November 28, 2019',
            'description' => 'BMI range according to age. Personnel above the acceptable range may still be classified "Acceptable by Large Body Frame" (height-and-wrist ratio) or "Acceptable by Waist Circumference" when there is no bulging abdomen.',
            'effective_from' => self::EFFECTIVE_FROM,
            'is_active' => true,
            'is_provisional' => false,
        ]);

        $packages = InterventionPackage::query()->whereIn('code', ['A', 'B', 'C'])->pluck('id', 'code');
        $below = [ClassificationCategory::Below, true, $packages['A'] ?? null];
        $normal = [ClassificationCategory::Normal, false, null];
        $over = [ClassificationCategory::Above, true, $packages['B'] ?? null];
        $obese = [ClassificationCategory::Above, true, $packages['C'] ?? null];

        // label, [category, intervention, package], BMI from (inclusive), BMI to (exclusive), age from, age to, color
        $bands = [
            ['Severely Underweight', $below, null, 17, null, null, 'rose'],
            ['Underweight', $below, 17, 18.5, null, null, 'blue'],
            ['Normal', $normal, 18.5, 25, null, null, 'green'],

            // Acceptable BMI: 24.9 (29 y/o & below, i.e. none above Normal), 25, 25–25.5, 25–26, 25–26.5, 25–27.
            ['Acceptable BMI', $normal, 25, 25.1, 30, 34, 'sky'],
            ['Acceptable BMI', $normal, 25, 25.6, 35, 39, 'sky'],
            ['Acceptable BMI', $normal, 25, 26.1, 40, 44, 'sky'],
            ['Acceptable BMI', $normal, 25, 26.6, 45, 50, 'sky'],
            ['Acceptable BMI', $normal, 25, 27.1, 51, null, 'sky'],

            // Overweight: 25–29.9, 25.1–29.9, 25.6–29.9, 26.1–29.9, 26.6–29.9, 27.1–29.9.
            ['Overweight', $over, 25, 30, null, 29, 'amber'],
            ['Overweight', $over, 25.1, 30, 30, 34, 'amber'],
            ['Overweight', $over, 25.6, 30, 35, 39, 'amber'],
            ['Overweight', $over, 26.1, 30, 40, 44, 'amber'],
            ['Overweight', $over, 26.6, 30, 45, 50, 'amber'],
            ['Overweight', $over, 27.1, 30, 51, null, 'amber'],

            ['Obese Class 1', $obese, 30, 35, null, null, 'orange'],
            ['Obese Class 2', $obese, 35, 40, null, null, 'red'],
            ['Obese Class 3', $obese, 40, null, null, null, 'red'],
        ];

        foreach ($bands as $order => [$label, [$category, $intervention, $packageId], $min, $max, $minAge, $maxAge, $color]) {
            $standard->classifications()->create([
                'label' => $label,
                'category' => $category,
                'min_bmi' => $min,
                'max_bmi' => $max,
                'min_age' => $minAge,
                'max_age' => $maxAge,
                'color' => $color,
                'requires_intervention' => $intervention,
                'intervention_package_id' => $packageId,
                'sort_order' => $order + 1,
            ]);
        }
    }
};
