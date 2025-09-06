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
        Schema::create('cultural_workshops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained('heritage_villages')->onDelete('cascade');
            $table->string('title_en', 255);
            $table->string('title_ar', 255);
            $table->text('description_en');
            $table->text('description_ar');
            $table->string('instructor_name', 255);
            $table->string('image_url', 500);
            $table->integer('duration_minutes');
            $table->integer('max_participants');
            $table->decimal('price_omr', 10, 3);
            $table->string('skill_level', 50);
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
        Schema::dropIfExists('cultural_workshops');
    }
};
