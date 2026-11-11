<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each category gets its own hero photo and a short line for the homepage card.
        Schema::table('service_categories', function (Blueprint $table) {
            $table->string('tagline', 150)->nullable()->after('description');
            $table->string('image')->nullable()->after('tagline');
        });

        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->string('thumb')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('caption', 150)->nullable();
            $table->foreignId('service_category_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_images');

        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'image']);
        });
    }
};
