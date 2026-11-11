<?php

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
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Auth;

/**
 * Fictitious demonstration accounts and personnel (no real personnel data; names are randomly combined).
 * It is a migration, not a seeder, so a demo deployment that only runs `php artisan migrate` (e.g.
 * Laravel Cloud) is ready to use. Set BMI_DEMO_DATA=false before the first migration of a real
 * production deployment: the demo accounts all use the password "password".
 *
 * Uses a seeded pseudo-random generator instead of Faker, which is a development-only package.
 */
return new class extends Migration
{
    private const SURNAMES = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Castillo', 'Flores', 'Villanueva', 'Ramos', 'Aquino', 'Navarro', 'Salazar', 'Domingo', 'Mercado', 'Aguilar', 'Pascual', 'Soriano', 'Valdez', 'Gonzales', 'Manalo', 'Dizon', 'Lopez', 'Tolentino', 'Rivera', 'Del Rosario'];

    private const MALE_NAMES = ['Mark', 'John Paul', 'Jerome', 'Ramil', 'Joel', 'Arnel', 'Ronald', 'Christian', 'Michael', 'Rodel', 'Jayson', 'Alvin', 'Noel', 'Dennis', 'Carlo', 'Romeo'];

    private const FEMALE_NAMES = ['Maria Lourdes', 'Kristine', 'Angelica', 'Jocelyn', 'Rowena', 'Grace', 'Liza', 'Marites', 'Catherine', 'Jennifer', 'Rachel', 'Shiela'];

    /**
     * The two default demo samples. Their weigh-in QR codes are fixed so the demo weigh-in photos
     * ("Demo 1 - Daniel" and "Demo 2 - Elena", scale reading 59.80 kg) identify them on any deployment.
     * Their weight history stays close to that reading and leaves the current month open, so a demo
     * photo is saved straight away.
     *
     * @var array<int, array{qr_token: string, rank: string, unit: string, last_name: string, first_name: string, middle_name: string, sex: Sex, height_cm: int, weight_kg: float}>
     */
    private const DEMO_SAMPLES = [
        1 => ['qr_token' => 'Y4W0C4IG09PI0', 'rank' => 'PCpl', 'unit' => 'ITMS', 'last_name' => 'Demo', 'first_name' => 'Daniel', 'middle_name' => 'Personnel', 'sex' => Sex::Male, 'height_cm' => 165, 'weight_kg' => 59.6],
        2 => ['qr_token' => '8Y5POFF0DE8R9', 'rank' => 'Pat', 'unit' => 'FS', 'last_name' => 'Sample', 'first_name' => 'Elena', 'middle_name' => 'Test', 'sex' => Sex::Female, 'height_cm' => 158, 'weight_kg' => 59.9],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! config('app.demo_data')) {
            return;
        }

        $assessments = app(AssessmentService::class);
        $monitoring = app(MonthlyMonitoringService::class);
        $interventions = app(InterventionService::class);

        mt_srand(20261104);

        $units = Unit::query()->pluck('id', 'code');
        $ranks = Rank::query()->pluck('id', 'abbreviation');

        $this->user('System Administrator', 'admin@bmi.test', Role::SuperAdmin);
        $hsAdmin = $this->user('PLTCOL Ana Health Admin', 'hsadmin@bmi.test', Role::HealthServiceAdmin, $units['HS']);
        $encoder = $this->user('PSSg Ben Encoder', 'encoder@bmi.test', Role::HealthServiceEncoder, $units['HS']);
        $this->user('PCPT Carla Unit Representative', 'unitrep@bmi.test', Role::UnitRepresentative, $units['ITMS']);
        $sampleUsers = [
            1 => $this->user('PCpl Daniel Demo Personnel', 'personnel@bmi.test', Role::Personnel),
            2 => $this->user('Pat Elena Sample', 'personnel2@bmi.test', Role::Personnel),
        ];

        Auth::setUser($encoder);

        $nonCommissioned = ['PCpl', 'PCpl', 'Pat', 'Pat', 'PSSg', 'PSSg', 'PMSg', 'PSMS', 'PCMS', 'NUP'];
        $commissioned = ['PLT', 'PCPT', 'PMAJ', 'PLTCOL'];
        $unitCodes = $units->keys()->all();

        $today = CarbonImmutable::today();
        $packages = InterventionPackage::query()->get()->keyBy('code');

        for ($i = 1; $i <= 48; $i++) {
            $sex = $this->chance(78) ? Sex::Male : Sex::Female;
            $rankCode = $this->chance(82) ? $this->pick($nonCommissioned) : $this->pick($commissioned);

            $attributes = [
                'employee_number' => sprintf('DEMO-%05d', $i),
                'rank_id' => $ranks[$rankCode],
                'unit_id' => $units[$unitCodes[$i % count($unitCodes)]],
                'last_name' => $this->pick(self::SURNAMES),
                'first_name' => $this->pick($sex === Sex::Male ? self::MALE_NAMES : self::FEMALE_NAMES),
                'middle_name' => $this->pick(self::SURNAMES),
                'sex' => $sex,
                'date_of_birth' => $today->subYears(mt_rand(23, 54))->subDays(mt_rand(0, 364))->format('Y-m-d'),
                'position' => $this->pick(['Admin PNCO', 'Records PNCO', 'IT Support PNCO', 'Investigator', 'Supply PNCO', 'Finance PNCO', 'Clerk']),
                'contact_number' => '09'.str_pad((string) mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT),
                'status' => PersonnelStatus::Active,
            ];

            $sample = self::DEMO_SAMPLES[$i] ?? null;

            if ($sample) {
                $attributes = array_merge($attributes, [
                    'rank_id' => $ranks[$sample['rank']],
                    'unit_id' => $units[$sample['unit']],
                    'last_name' => $sample['last_name'],
                    'first_name' => $sample['first_name'],
                    'middle_name' => $sample['middle_name'],
                    'sex' => $sample['sex'],
                ]);
            }

            $personnel = Personnel::updateOrCreate(['employee_number' => $attributes['employee_number']], $attributes);

            if ($sample) {
                $personnel->forceFill(['user_id' => $sampleUsers[$i]->id, 'qr_token' => $sample['qr_token']])->save();
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

            $this->seedHistory($personnel, $sample, $assessments, $monitoring, $interventions, $hsAdmin, $encoder, $today);
        }

        Auth::guard()->forgetUser();
        mt_srand();
    }

    /**
     * Reverse the migrations. Demo records are removed by deleting them in the application (or by
     * rebuilding the database); they are not dropped automatically because they may have been edited.
     */
    public function down(): void
    {
        //
    }

    /**
     * Two years of assessments and monthly weigh-ins, with an intervention where the result calls for one.
     *
     * @param  array{height_cm: int, weight_kg: float}|null  $sample  fixed values for a default demo sample
     */
    private function seedHistory(Personnel $personnel, ?array $sample, AssessmentService $assessments, MonthlyMonitoringService $monitoring, InterventionService $interventions, User $hsAdmin, User $encoder, CarbonImmutable $today): void
    {
        $height = mt_rand($personnel->sex === Sex::Male ? 160 : 150, $personnel->sex === Sex::Male ? 182 : 170);
        $targetBmi = $this->pick([20.5, 21.5, 22.5, 23.2, 24.0, 24.4, 21.0, 22.0, 26.0, 27.5, 28.8, 31.0, 17.9, 23.8, 25.6]);
        $weight = round($targetBmi * ($height / 100) ** 2, 2);
        $drift = $this->decimal(-0.25, 0.3);

        $assessmentMonths = [CarbonImmutable::create(2025, 1, mt_rand(6, 20)), CarbonImmutable::create(2026, 1, mt_rand(6, 20))];

        if ($this->chance(35) && ! $sample) {
            $assessmentMonths[] = CarbonImmutable::create(2026, 10, mt_rand(5, 20));
        }

        if ($sample) {
            $height = $sample['height_cm'];
        }

        $period = CarbonImmutable::create(2025, 1, 1);

        while ($period->lte($today->startOfMonth())) {
            $weight = $sample
                ? round($sample['weight_kg'] + $this->decimal(-0.5, 0.5), 2)
                : round(max(40, $weight + $drift + $this->decimal(-0.7, 0.7)), 2);

            foreach ($assessmentMonths as $index => $date) {
                if ($date->format('Y-m') !== $period->format('Y-m') || $date->gt($today)) {
                    continue;
                }

                $assessment = $assessments->create($personnel, [
                    'assessed_on' => $date->format('Y-m-d'),
                    'height_cm' => $height,
                    'weight_kg' => $weight,
                    'waist_cm' => round($weight * 1.15 + mt_rand(-4, 4), 1),
                    'hip_cm' => round($weight * 1.3 + mt_rand(-4, 4), 1),
                    'wrist_cm' => mt_rand(14, 18),
                    'unit_representative_name' => 'PCPT Carla Unit Representative',
                    'unit_representative_designation' => 'Unit Representative',
                    'health_service_representative_name' => $hsAdmin->name,
                    'health_service_representative_designation' => 'Health Service Representative',
                ], submit: true, recordMonthlyWeight: true);

                $isLatestRound = $index === count($assessmentMonths) - 1 && $date->year === 2026 && $date->month === 10;

                if (! $isLatestRound || $this->chance(40)) {
                    Auth::setUser($hsAdmin);
                    $assessments->certify($assessment, $hsAdmin);
                    Auth::setUser($encoder);
                }
            }

            $isCurrentMonth = $period->isSameMonth($today);
            $recordThisMonth = match (true) {
                (bool) $sample => ! $isCurrentMonth,
                $isCurrentMonth => $this->chance(30),
                default => $this->chance($period->year === 2026 && $period->month === 10 ? 86 : 93),
            };

            if ($recordThisMonth && ! $monitoring->exists($personnel, $period->year, $period->month)) {
                $measuredOn = $period->setDay(mt_rand(2, $isCurrentMonth ? max(2, $today->day) : 24));
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
                $interventions->recordFollowUp($intervention, $followUpDate, round($latest->weight_kg - $this->decimal(0, 2, 1), 2), 'Progress reviewed with personnel.', $followUpDate->addDays(30));
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

    private function chance(int $percent): bool
    {
        return mt_rand(1, 100) <= $percent;
    }

    /**
     * @template T
     *
     * @param  list<T>  $options
     * @return T
     */
    private function pick(array $options): mixed
    {
        return $options[mt_rand(0, count($options) - 1)];
    }

    private function decimal(float $min, float $max, int $decimals = 2): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), $decimals);
    }
};
