<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // The address given for this booking. Kept per booking because online
            // customers are matched by phone alone: typing someone's number must
            // not redirect that customer's future mail.
            $table->string('contact_email', 120)->nullable()->after('notes');
            $table->timestamp('confirmation_sent_at')->nullable()->after('contact_email');
            $table->string('confirmation_error', 255)->nullable()->after('confirmation_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'confirmation_sent_at', 'confirmation_error']);
        });
    }
};
