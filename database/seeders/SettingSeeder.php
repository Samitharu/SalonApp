<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
        ['key' => 'salon_name', 'value' => 'Negombo Saloon'],
        ['key' => 'salon_phone', 'value' => '+94 77 000 0000'],
        ['key' => 'salon_address', 'value' => 'Negombo, Sri Lanka'],
        ['key' => 'slot_interval_minutes', 'value' => '15'], // common real systems use 15 mins
        ['key' => 'currency', 'value' => 'LKR'],
        ['key' => 'max_parallel_bookings', 'value' => '3'],
        
    ];
    foreach ($data as $row) {
        Setting::updateOrCreate(['key' => $row['key']], ['value' => $row['value']]);
    }
    }
}
