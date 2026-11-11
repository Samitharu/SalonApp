<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Defaults only — existing values are never overwritten.
     */
    public function run(): void
    {
        $defaults = [
            'salon_name'            => 'Negombo Saloon',
            'salon_tagline'         => 'Hair, skin and nail care by people who love what they do.',
            'salon_phone'           => '+94 77 000 0000',
            'salon_address'         => 'Negombo, Sri Lanka',
            'currency'              => 'LKR',
            'max_parallel_bookings' => '3',
            'max_advance_days'      => '90',
            'min_notice_minutes'    => '30',
            'cancellation_hours'    => '2',
            'slot_step'             => '0',
            'auto_confirm'          => '0',
            'booking_policy'        => 'Please arrive 5 minutes early. Bookings can be cancelled online up to 2 hours before the appointment.',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
