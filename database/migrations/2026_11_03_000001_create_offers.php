<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('discount_type', 10);   // percent | fixed
            $table->decimal('discount_value', 10, 2);
            // Visits on these dates get the offer, both days included.
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('applies_to', 10)->default('all');   // all | selected
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_on', 'ends_on']);
        });

        Schema::create('offer_service', function (Blueprint $table) {
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['offer_id', 'service_id']);
        });

        // Saved on the booking, so its price holds after the offer ends, changes or is deleted.
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('offer_id')->nullable()->after('service_id')->constrained()->nullOnDelete();
            $table->string('offer_name', 120)->nullable()->after('offer_id');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('offer_name');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offer_id');
            $table->dropColumn(['offer_name', 'discount_amount']);
        });

        Schema::dropIfExists('offer_service');
        Schema::dropIfExists('offers');
    }
};
