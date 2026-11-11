<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Baseline data for a fresh install. Create the first admin with:
     *   php artisan salon:admin
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            BusinessHourSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(StaffSeeder::class);
        }
    }
}
