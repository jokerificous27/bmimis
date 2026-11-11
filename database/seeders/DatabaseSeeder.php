<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Reference data is always seeded. Fictitious demonstration accounts and personnel are only seeded
     * outside production; create the first production administrator with `php artisan bmi:create-admin`.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
