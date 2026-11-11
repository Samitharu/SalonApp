<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sample stylists for local development only.
 */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $staff = [
            ['name' => 'Nimal Perera', 'title' => 'Senior Stylist', 'phone' => '0771111111'],
            ['name' => 'Kasuni Silva', 'title' => 'Colour Specialist', 'phone' => '0772222222'],
            ['name' => 'Saman Fernando', 'title' => 'Barber', 'phone' => '0773333333'],
        ];

        foreach ($staff as $s) {
            Staff::firstOrCreate(['phone' => $s['phone']], $s + [
                'is_active'      => true,
                'table_qr_token' => Str::random(40),
            ]);
        }
    }
}
