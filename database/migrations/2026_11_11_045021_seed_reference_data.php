<?php

use App\Enums\ClassificationCategory;
use App\Enums\StandardType;
use App\Models\BmiStandard;
use App\Models\InterventionPackage;
use App\Models\Rank;
use App\Models\RolePermission;
use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;

/**
 * Reference data required by the application (ranks, sample units and packages, BMI standards, default
 * role permissions). It is a migration, not a seeder, so it also runs on platforms that only run
 * `php artisan migrate` on deploy (e.g. Laravel Cloud). Idempotent: existing rows are kept.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->seedRanks();
        $this->seedUnits();
        $packages = $this->seedInterventionPackages();
        $this->seedWhoStandard();
        $this->seedProvisionalPnpStandard($packages);

        RolePermission::seedDefaults();
    }

    /**
     * Reverse the migrations. Reference data may already be in use by records, so it is left in place.
     */
    public function down(): void
    {
        //
    }

    /**
     * PNP ranks under Republic Act No. 11200 plus Non-Uniformed Personnel.
     */
    private function seedRanks(): void
    {
        $ranks = [
            ['PGEN', 'Police General', 'Commissioned Officer'],
            ['PLTGEN', 'Police Lieutenant General', 'Commissioned Officer'],
            ['PMGEN', 'Police Major General', 'Commissioned Officer'],
            ['PBGEN', 'Police Brigadier General', 'Commissioned Officer'],
            ['PCOL', 'Police Colonel', 'Commissioned Officer'],
            ['PLTCOL', 'Police Lieutenant Colonel', 'Commissioned Officer'],
            ['PMAJ', 'Police Major', 'Commissioned Officer'],
            ['PCPT', 'Police Captain', 'Commissioned Officer'],
            ['PLT', 'Police Lieutenant', 'Commissioned Officer'],
            ['PEMS', 'Police Executive Master Sergeant', 'Non-Commissioned Officer'],
            ['PCMS', 'Police Chief Master Sergeant', 'Non-Commissioned Officer'],
            ['PSMS', 'Police Senior Master Sergeant', 'Non-Commissioned Officer'],
            ['PMSg', 'Police Master Sergeant', 'Non-Commissioned Officer'],
            ['PSSg', 'Police Staff Sergeant', 'Non-Commissioned Officer'],
            ['PCpl', 'Police Corporal', 'Non-Commissioned Officer'],
            ['Pat', 'Patrolman / Patrolwoman', 'Non-Commissioned Officer'],
            ['NUP', 'Non-Uniformed Personnel', 'Non-Uniformed Personnel'],
        ];

        foreach ($ranks as $order => [$abbreviation, $name, $category]) {
            Rank::firstOrCreate(['abbreviation' => $abbreviation], [
                'name' => $name,
                'category' => $category,
                'sort_order' => $order + 1,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Sample offices. Edit or replace them in Administration → Unit Management.
     */
    private function seedUnits(): void
    {
        // Regional offices and services
        $units = [
            ['HS', 'Health Service'],
            ['ITMS', 'Information Technology Management Service'],
            ['DPRM', 'Directorate for Personnel and Records Management'],
            ['LSS', 'Logistics Support Service'],
            ['FS', 'Finance Service'],
            ['CES', 'Communications and Electronics Service'],
        ];

        // PRO2 Provincial Offices, City Office, and Police Stations
        $territorialUnits = [

            'BPPO' => [
                'name' => 'Batanes Police Provincial Office',
                'stations' => [
                    'Basco PS',
                    'Itbayat PS',
                    'Ivana PS',
                    'Mahatao PS',
                    'Sabtang PS',
                    'Uyugan PS',
                ],
            ],

            'CPPO' => [
                'name' => 'Cagayan Police Provincial Office',
                'stations' => [
                    'Abulug PS',
                    'Alcala PS',
                    'Allacapan PS',
                    'Amulung PS',
                    'Aparri PS',
                    'Baggao PS',
                    'Ballesteros PS',
                    'Buguey PS',
                    'Calayan PS',
                    'Camalaniugan PS',
                    'Claveria PS',
                    'Enrile PS',
                    'Gattaran PS',
                    'Gonzaga PS',
                    'Iguig PS',
                    'Lallo PS',
                    'Lasam PS',
                    'Pamplona PS',
                    'Peñablanca PS',
                    'Piat PS',
                    'Rizal PS',
                    'Sanchez Mira PS',
                    'Solana PS',
                    'Sta Ana PS',
                    'Sta Praxedes PS',
                    'Sta Teresita PS',
                    'Sto Niño PS',
                    'Tuao PS',
                    'Tuguegarao PS',
                ],
            ],

            'IPPO' => [
                'name' => 'Isabela Police Provincial Office',
                'stations' => [
                    'Alicia PS',
                    'Angadanan PS',
                    'Aurora PS',
                    'Benito Soliven PS',
                    'Burgos PS',
                    'Cabagan PS',
                    'Cabatuan PS',
                    'Cauayan PS',
                    'Cordon PS',
                    'Delfin Albano PS',
                    'Dinapigue PS',
                    'Divilacan PS',
                    'Echague PS',
                    'Gamu PS',
                    'Ilagan PS',
                    'Jones PS',
                    'Luna PS',
                    'Maconacon PS',
                    'Mallig PS',
                    'Naguilian PS',
                    'Palanan PS',
                    'Quezon PS (ISABELA)',
                    'Quirino PS',
                    'Ramon PS',
                    'Reina Mercedes PS',
                    'Roxas PS',
                    'San Agustin PS',
                    'San Guillermo PS',
                    'San Isidro PS',
                    'San Manuel PS',
                    'San Mariano PS',
                    'San Mateo PS',
                    'San Pablo PS',
                    'Sta Maria PS',
                    'Sto Tomas PS',
                    'Tumauini PS',
                ],
            ],

            'NVPPO' => [
                'name' => 'Nueva Vizcaya Police Provincial Office',
                'stations' => [
                    'Alfonso Castañeda PS',
                    'Ambaguio PS',
                    'Aritao PS',
                    'Bagabag PS',
                    'Bambang PS',
                    'Bayombong PS',
                    'Diadi PS',
                    'Dupax Del Norte PS',
                    'Dupax Del Sur PS',
                    'Kasibu PS',
                    'Kayapa PS',
                    'Quezon PS (NUEVA VIZCAYA)',
                    'Solano PS',
                    'Sta Fe PS',
                    'Villaverde PS',
                ],
            ],

            'QPPO' => [
                'name' => 'Quirino Police Provincial Office',
                'stations' => [
                    'Aglipay PS',
                    'Cabarroguis PS',
                    'Diffun PS',
                    'Maddela PS',
                    'Nagtipunan PS',
                    'Saguday PS',
                ],
            ],

            'SCPO' => [
                'name' => 'Santiago City Police Office',
                'stations' => [
                    'PS 01',
                    'PS 02',
                    'PS 03',
                    'PS 04',
                ],
            ],
        ];

        // Add provincial/city offices and their police stations
        foreach ($territorialUnits as $officeCode => $office) {

            // Add the parent provincial/city office
            $units[] = [
                $officeCode,
                $office['name'],
            ];

            // Add police stations under the office
            foreach ($office['stations'] as $index => $station) {

                $stationCode = $officeCode.'-PS-'.
                    str_pad($index + 1, 2, '0', STR_PAD_LEFT);

                $units[] = [
                    $stationCode,
                    $station,
                ];
            }
        }

        // Insert all units into the database
        foreach ($units as [$code, $name]) {
            Unit::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Sample packages only. Official package descriptions must be encoded by the Health Service.
     *
     * @return array<string, InterventionPackage>
     */
    private function seedInterventionPackages(): array
    {
        $packages = [
            'A' => 'Sample package A',
            'B' => 'Sample package B',
            'C' => 'Sample package C',
        ];

        return collect($packages)->map(fn (string $name, string $code) => InterventionPackage::firstOrCreate(['code' => $code], [
            'name' => $name,
            'description' => 'SAMPLE ENTRY – replace with the official package description issued by the PNP Health Service.',
            'follow_up_interval_days' => 30,
            'is_active' => true,
        ]))->all();
    }

    /**
     * WHO adult BMI classification (kg/m²).
     */
    private function seedWhoStandard(): void
    {
        if (BmiStandard::query()->where('type', StandardType::Who->value)->exists()) {
            return;
        }

        $standard = BmiStandard::create([
            'type' => StandardType::Who,
            'name' => 'WHO Adult BMI Classification',
            'reference' => 'World Health Organization – Body mass index classification for adults',
            'description' => 'International adult BMI cut-off points.',
            'effective_from' => '2000-01-01',
            'is_active' => true,
            'is_provisional' => false,
        ]);

        $this->createBands($standard, [
            ['Underweight', ClassificationCategory::Below, null, 18.5, 'amber', false, null],
            ['Normal', ClassificationCategory::Normal, 18.5, 25, 'green', false, null],
            ['Pre-obese', ClassificationCategory::Above, 25, 30, 'orange', false, null],
            ['Obese Class I', ClassificationCategory::Above, 30, 35, 'red', false, null],
            ['Obese Class II', ClassificationCategory::Above, 35, 40, 'red', false, null],
            ['Obese Class III', ClassificationCategory::Above, 40, null, 'rose', false, null],
        ]);
    }

    /**
     * A PROVISIONAL PNP standard that mirrors WHO cut-offs so the system is usable out of the box.
     * It is flagged as provisional and must be replaced with the official PNP Health Service thresholds.
     *
     * @param  array<string, InterventionPackage>  $packages
     */
    private function seedProvisionalPnpStandard(array $packages): void
    {
        if (BmiStandard::query()->where('type', StandardType::Pnp->value)->exists()) {
            return;
        }

        $standard = BmiStandard::create([
            'type' => StandardType::Pnp,
            'name' => 'PNP Acceptable BMI (PROVISIONAL)',
            'reference' => null,
            'description' => 'Provisional thresholds mirroring WHO cut-offs. Replace with the official values from the applicable PNP Health Service policy.',
            'effective_from' => '2000-01-01',
            'is_active' => true,
            'is_provisional' => true,
        ]);

        $this->createBands($standard, [
            ['Underweight', ClassificationCategory::Below, null, 18.5, 'amber', true, $packages['A']->id],
            ['Normal', ClassificationCategory::Normal, 18.5, 25, 'green', false, null],
            ['Overweight', ClassificationCategory::Above, 25, 30, 'orange', true, $packages['B']->id],
            ['Obese', ClassificationCategory::Above, 30, null, 'red', true, $packages['C']->id],
        ]);
    }

    /**
     * @param  list<array{0: string, 1: ClassificationCategory, 2: float|null, 3: float|null, 4: string, 5: bool, 6: int|null}>  $bands
     */
    private function createBands(BmiStandard $standard, array $bands): void
    {
        foreach ($bands as $order => [$label, $category, $min, $max, $color, $requiresIntervention, $packageId]) {
            $standard->classifications()->create([
                'label' => $label,
                'category' => $category,
                'min_bmi' => $min,
                'max_bmi' => $max,
                'color' => $color,
                'requires_intervention' => $requiresIntervention,
                'intervention_package_id' => $packageId,
                'sort_order' => $order + 1,
            ]);
        }
    }
};
