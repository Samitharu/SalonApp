<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $staff = [
        ['name' => 'Nimal', 'phone' => '0771111111'],
        ['name' => 'Kasun', 'phone' => '0772222222'],
        ['name' => 'Saman', 'phone' => '0773333333'],
    ];

    foreach ($staff as $s) {
        Staff::create([
            'name' => $s['name'],
            'phone' => $s['phone'],
            'is_active' => 1,
        ]);
    }
    }
}
