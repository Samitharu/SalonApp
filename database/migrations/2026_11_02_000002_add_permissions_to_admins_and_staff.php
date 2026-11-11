<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_owner');
            $table->json('permissions')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('permissions');
            // "Keep me signed in" on the login page needs this column.
            $table->rememberToken()->after('last_login_at');
        });

        // Everyone who could sign in until now had full access, so nobody loses anything.
        DB::table('admins')->update(['is_owner' => true]);

        Schema::table('staff', function (Blueprint $table) {
            // Null means the default: everything the stylist portal offers.
            $table->json('portal_permissions')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['is_owner', 'is_active', 'permissions', 'last_login_at', 'remember_token']);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('portal_permissions');
        });
    }
};
