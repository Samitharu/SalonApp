<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only audit trail + application errors. No foreign keys on
        // purpose: entries must outlive the records they describe.
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 10)->default('info');      // info | warning | error | critical
            $table->string('category', 30);                    // auth, booking, payment, staff, …
            $table->string('action', 60);                      // e.g. appointment.created
            $table->string('description', 500);
            $table->string('actor_type', 20)->nullable();      // admin | staff | cashier | customer | guest | system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 120)->nullable();     // snapshot, survives renames/deletes
            $table->string('subject_type', 40)->nullable();    // Appointment, Staff, Service, …
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();            // changed fields, amounts, error context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['category', 'created_at']);
            $table->index(['level', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
