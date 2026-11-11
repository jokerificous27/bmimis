<?php

namespace Database\Seeders;

use App\Enums\PersonnelStatus;
use App\Enums\Role;
use App\Enums\Sex;
use App\Models\BmiAssessment;
use App\Models\InterventionPackage;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\Rank;
use App\Models\Unit;
use App\Models\User;
use App\Services\AssessmentService;
use App\Services\InterventionService;
use App\Services\MonthlyMonitoringService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Fictitious demonstration accounts and personnel for local development only.
 * No real personnel data is used; names are randomly combined.
 */
class DemoDataSeeder extends Seeder
{
    private const SURNAMES = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Castillo', 'Flores', 'Villanueva', 'Ramos', 'Aquino', 'Navarro', 'Salazar', 'Domingo', 'Mercado', 'Aguilar', 'Pascual', 'Soriano', 'Valdez', 'Gonzales', 'Manalo', 'Dizon', 'Lopez', 'Tolentino', 'Rivera', 'Del Rosario'];

    private const MALE_NAMES = ['Mark', 'John Paul', 'Jerome', 'Ramil', 'Joel', 'Arnel', 'Ronald', 'Christian', 'Michael', 'Rodel', 'Jayson', 'Alvin', 'Noel', 'Dennis', 'Carlo', 'Romeo'];

    private const FEMALE_NAMES = ['Maria Lourdes', 'Kristine', 'Angelica', 'Jocelyn', 'Rowena', 'Grace', 'Liza', 'Marites', 'Catherine', 'Jennifer', 'Rachel', 'Shiela'];

    public function run(AssessmentService $assessments, MonthlyMonitoringService $monitoring, InterventionService $interventions): void
    {
        fake()->seed(20261104);

        $units = Unit::query()->pluck('id', 'code');
        $ranks = Rank::query()->pluck('id', 'abbreviation');

        $admin = $this->user('System Administrator', 'admin@bmi.test', Role::SuperAdmin);
        $hsAdmin = $this->user('PLTCOL Ana Health Admin', 'hsadmin@bmi.test', Role::HealthServiceAdmin, $units['HS']);
        $encoder = $this->user('PSSg Ben Encoder', 'encoder@bmi.test', Role::HealthServiceEncoder, $units['HS']);
        $this->user('PCPT Carla Unit Representative', 'unitrep@bmi.test', Role::UnitRepresentative, $units['ITMS']);
        $personnelUser = $this->user('PCpl Daniel Demo Personnel', 'personnel@bmi.test', Role::Personnel);
        $otherPersonnelUser = $this->user('Pat Elena Sample', 'personnel2@bmi.test', Role::Personnel);

        Auth::setUser($encoder);

        $nonCommissioned = ['PCpl', 'PCpl', 'Pat', 'Pat', 'PSSg', 'PSSg', 'PMSg', 'PSMS', 'PCMS', 'NUP'];
        $commissioned = ['PLT', 'PCPT', 'PMAJ', 'PLTCOL'];
        $unitCodes = $units->keys()->all();

        $today = CarbonImmutable::today();
        $packages = InterventionPackage::query()->get()->keyBy('code');

        for ($i = 1; $i <= 48; $i++) {
            $sex = fake()->boolean(78) ? Sex::Male : Sex::Female;
            $rankCode = fake()->boolean(82) ? fake()->randomElement($nonCommissioned) : fake()->randomElement($commissioned);

            $attributes = [
                'employee_number' => sprintf('DEMO-%05d', $i),
                'rank_id' => $ranks[$rankCode],
                'unit_id' => $units[$unitCodes[$i % count($unitCodes)]],
                'last_name' => fake()->randomElement(self::SURNAMES),
                'first_name' => fake()->randomElement($sex === Sex::Male ? self::MALE_NAMES : self::FEMALE_NAMES),
                'middle_name' => fake()->randomElement(self::SURNAMES),
                'sex' => $sex,
                'date_of_birth' => $today->subYears(fake()->numberBetween(23, 54))->subDays(fake()->numberBetween(0, 364))->format('Y-m-d'),
                'position' => fake()->randomElement(['Admin PNCO', 'Records PNCO', 'IT Support PNCO', 'Investigator', 'Supply PNCO', 'Finance PNCO', 'Clerk']),
                'contact_number' => fake()->numerify('09#########'),
                'status' => PersonnelStatus::Active,
            ];

            if ($i === 1) {
                $attributes = array_merge($attributes, ['rank_id' => $ranks['PCpl'], 'unit_id' => $units['ITMS'], 'last_name' => 'Demo', 'first_name' => 'Daniel', 'middle_name' => 'Personnel', 'sex' => Sex::Male]);
            }

            if ($i === 2) {
                $attributes = array_merge($attributes, ['rank_id' => $ranks['Pat'], 'unit_id' => $units['FS'], 'last_name' => 'Sample', 'first_name' => 'Elena', 'middle_name' => 'Test', 'sex' => Sex::Female]);
            }

            $personnel = Personnel::updateOrCreate(['employee_number' => $attributes['employee_number']], $attributes);

            if ($i === 1) {
                $personnel->forceFill(['user_id' => $personnelUser->id])->save();
            }

            if ($i === 2) {
                $personnel->forceFill(['user_id' => $otherPersonnelUser->id])->save();
            }

            if ($personnel->assessments()->exists()) {
                continue;
            }

            PersonnelAssignment::create([
                'personnel_id' => $personnel->id,
                'unit_id' => $personnel->unit_id,
                'rank_id' => $personnel->rank_id,
                'position' => $personnel->position,
                'effective_from' => '2024-01-01',
                'remarks' => 'Initial record',
                'created_by' => $encoder->id,
            ]);

            $this->seedHistory($personnel, $assessments, $monitoring, $interventions, $hsAdmin, $encoder, $packages->all(), $today);
        }

        Auth::setUser($admin);
    }

    /**
     * @param  array<string, InterventionPackage>  $packages
     */
    private function seedHistory(Personnel $personnel, AssessmentService $assessments, MonthlyMonitoringService $monitoring, InterventionService $interventions, User $hsAdmin, User $encoder, array $packages, CarbonImmutable $today): void
    {
        $height = fake()->numberBetween($personnel->sex === Sex::Male ? 160 : 150, $personnel->sex === Sex::Male ? 182 : 170);
        $targetBmi = fake()->randomElement([20.5, 21.5, 22.5, 23.2, 24.0, 24.4, 21.0, 22.0, 26.0, 27.5, 28.8, 31.0, 17.9, 23.8, 25.6]);
        $weight = round($targetBmi * ($height / 100) ** 2, 2);
        $drift = fake()->randomFloat(2, -0.25, 0.3);

        $assessmentMonths = [CarbonImmutable::create(2025, 1, fake()->numberBetween(6, 20)), CarbonImmutable::create(2026, 1, fake()->numberBetween(6, 20))];

        if (fake()->boolean(35)) {
            $assessmentMonths[] = CarbonImmutable::create(2026, 10, fake()->numberBetween(5, 20));
        }

        $period = CarbonImmutable::create(2025, 1, 1);

        while ($period->lte($today->startOfMonth())) {
            $weight = round(max(40, $weight + $drift + fake()->randomFloat(2, -0.7, 0.7)), 2);

            foreach ($assessmentMonths as $index => $date) {
                if ($date->format('Y-m') !== $period->format('Y-m')) {
                    continue;
                }

                $assessment = $assessments->create($personnel, [
                    'assessed_on' => $date->format('Y-m-d'),
                    'height_cm' => $height,
                    'weight_kg' => $weight,
                    'waist_cm' => round($weight * 1.15 + fake()->numberBetween(-4, 4), 1),
                    'hip_cm' => round($weight * 1.3 + fake()->numberBetween(-4, 4), 1),
                    'wrist_cm' => fake()->numberBetween(14, 18),
                    'unit_representative_name' => 'PCPT Carla Unit Representative',
                    'unit_representative_designation' => 'Unit Representative',
                    'health_service_representative_name' => $hsAdmin->name,
                    'health_service_representative_designation' => 'Health Service Representative',
                ], submit: true, recordMonthlyWeight: true);

                $isLatestRound = $index === count($assessmentMonths) - 1 && $date->year === 2026 && $date->month === 10;

                if (! $isLatestRound || fake()->boolean(40)) {
                    Auth::setUser($hsAdmin);
                    $assessments->certify($assessment, $hsAdmin);
                    Auth::setUser($encoder);
                }
            }

            $isCurrentMonth = $period->isSameMonth($today);
            $recordThisMonth = $isCurrentMonth ? fake()->boolean(30) : fake()->boolean($period->year === 2026 && $period->month === 10 ? 86 : 93);

            if ($recordThisMonth && ! $monitoring->exists($personnel, $period->year, $period->month)) {
                $measuredOn = $period->setDay(fake()->numberBetween(2, $isCurrentMonth ? max(2, $today->day) : 24));
                $monitoring->record($personnel, $period->year, $period->month, $weight, $measuredOn->min($today), notify: false);
            }

            $period = $period->addMonth();
        }

        $latest = $personnel->latestAssessment()->first();

        if ($latest instanceof BmiAssessment && $latest->requires_intervention && $latest->intervention_package_id) {
            Auth::setUser($hsAdmin);
            $assigned = CarbonImmutable::parse($latest->assessed_on)->addDays(7);
            $intervention = $interventions->assign($personnel, InterventionPackage::find($latest->intervention_package_id), $assigned, $assigned->addDays(30), $latest);

            $followUpDate = $assigned->addDays(30);

            while ($followUpDate->lt($today->subDays(10))) {
                $interventions->recordFollowUp($intervention, $followUpDate, round($latest->weight_kg - fake()->randomFloat(1, 0, 2), 2), 'Progress reviewed with personnel.', $followUpDate->addDays(30));
                $followUpDate = $followUpDate->addDays(30);
            }

            Auth::setUser($encoder);
        }
    }

    private function user(string $name, string $email, Role $role, ?int $unitId = null): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => 'password',
            'email_verified_at' => now(),
            'role' => $role,
            'unit_id' => $unitId,
            'is_active' => true,
        ])->save();

        return $user;
    }
}
