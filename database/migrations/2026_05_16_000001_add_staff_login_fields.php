<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (!Schema::hasColumn('staff', 'login_username')) {
                $table->string('login_username', 60)->nullable()->unique()->after('email');
            }
            if (!Schema::hasColumn('staff', 'password')) {
                $table->string('password')->nullable()->after('login_username');
            }
            if (!Schema::hasColumn('staff', 'table_qr_token')) {
                $table->string('table_qr_token', 64)->nullable()->unique()->after('password');
            }
            if (!Schema::hasColumn('staff', 'remember_token')) {
                $table->rememberToken()->after('table_qr_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['login_username', 'password', 'table_qr_token', 'remember_token']);
        });
    }
};
