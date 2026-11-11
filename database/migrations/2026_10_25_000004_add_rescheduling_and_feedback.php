<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track changes to a booking (service, stylist, date/time).
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('rescheduled_at')->nullable()->after('cancelled_at');
            $table->string('rescheduled_by', 20)->nullable()->after('rescheduled_at'); // customer | admin
            $table->unsignedTinyInteger('reschedule_count')->default(0)->after('rescheduled_by');
        });

        // One review per completed appointment.
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');            // 1–5
            $table->text('comment')->nullable();
            $table->boolean('allow_public')->default(false);  // customer consent to show on the website
            $table->boolean('is_public')->default(false);     // admin chose to publish it
            $table->timestamps();

            $table->index(['staff_id', 'rating']);
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['rescheduled_at', 'rescheduled_by', 'reschedule_count']);
        });
    }
};
