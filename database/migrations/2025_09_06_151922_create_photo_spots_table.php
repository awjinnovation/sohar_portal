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
        Schema::create('photo_spots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained('heritage_villages')->onDelete('cascade');
            $table->string('name_en', 255);
            $table->string('name_ar', 255);
            $table->text('description_en');
            $table->text('description_ar');
            $table->string('image_url', 500);
            $table->string('best_time_for_photos', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('village_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photo_spots');
    }
};
