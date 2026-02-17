<?php

namespace Database\Seeders;

use App\Models\BusinessHour;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessHourSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    // Sunday (0) closed
    BusinessHour::updateOrCreate(
        ['day_of_week' => 0],
        ['is_closed' => true, 'open_time' => null, 'close_time' => null]
    );

    // Mon-Sat open 09:00 - 20:00
    for ($i = 1; $i <= 6; $i++) {
        BusinessHour::updateOrCreate(
            ['day_of_week' => $i],
            ['is_closed' => false, 'open_time' => '09:00:00', 'close_time' => '20:00:00']
        );
    }
}
}
