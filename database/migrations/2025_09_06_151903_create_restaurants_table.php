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
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('name_ar', 255);
            $table->text('description');
            $table->text('description_ar');
            $table->string('cuisine', 100);
            $table->string('cuisine_ar', 100);
            $table->string('location', 255);
            $table->string('location_ar', 255);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('rating', 2, 1)->default(0);
            $table->integer('total_ratings')->default(0);
            $table->enum('price_range', ['$', '$$', '$$$', '$$$$']);
            $table->string('image_url', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website', 255)->nullable();
            $table->boolean('is_open')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('cuisine');
            $table->index('rating');
            $table->index('price_range');
            $table->index('is_featured');
            $table->index('is_active');
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
