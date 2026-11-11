<?php

use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// ── Create Admin account ──────────────────────────────────────────────────
$admin = Admin::updateOrCreate(
    ['email' => 'admin@salon.com'],
    [
        'name'     => 'Salon Admin',
        'password' => Hash::make('admin123'),
        'is_owner' => true,
    ]
);
echo "✅ Admin created: admin@salon.com / admin123\n";

// ── Create a demo stylist with portal login ────────────────────────────────
$staff = Staff::updateOrCreate(
    ['email' => 'amaya@salon.com'],
    [
        'name'            => 'Amaya Perera',
        'phone'           => '077 111 2222',
        'bio'             => 'Senior Stylist',
        'is_active'       => true,
        'login_username'  => 'amaya.stylist',
        'password'        => Hash::make('stylist123'),
        'table_qr_token'  => Str::random(32),
    ]
);
echo "✅ Stylist created: username=amaya.stylist / password=stylist123\n";

// ── Create a second demo stylist ───────────────────────────────────────────
$staff2 = Staff::updateOrCreate(
    ['email' => 'nimal@salon.com'],
    [
        'name'            => 'Nimal Silva',
        'phone'           => '077 333 4444',
        'bio'             => 'Hair Color Specialist',
        'is_active'       => true,
        'login_username'  => 'nimal.stylist',
        'password'        => Hash::make('stylist123'),
        'table_qr_token'  => Str::random(32),
    ]
);
echo "✅ Stylist created: username=nimal.stylist / password=stylist123\n";

echo "\n=== LOGIN SUMMARY ===\n";
echo "Admin Portal  → /admin/login\n";
echo "  Email:    admin@salon.com\n";
echo "  Password: admin123\n\n";
echo "Stylist Portal → /stylist/login\n";
echo "  Username: amaya.stylist\n";
echo "  Password: stylist123\n\n";
echo "  Username: nimal.stylist\n";
echo "  Password: stylist123\n";
