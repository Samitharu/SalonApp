<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('services', function (Blueprint $table) {
        $table->id();
        $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();

        $table->string('name', 120);
        $table->string('slug', 150)->unique();

        $table->decimal('price', 10, 2)->default(0);
        $table->unsignedSmallInteger('duration_minutes')->default(30);

        $table->text('description')->nullable();
        $table->string('image', 255)->nullable();

        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
