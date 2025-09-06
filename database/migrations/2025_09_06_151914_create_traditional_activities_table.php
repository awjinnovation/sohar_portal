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
        Schema::create('traditional_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('heritage_village_id')->constrained('heritage_villages')->onDelete('cascade');
            $table->string('activity_name_en', 255);
            $table->string('activity_name_ar', 255);
            $table->text('description_en');
            $table->text('description_ar');
            $table->string('image_url', 500);
            $table->boolean('is_interactive')->default(false);
            $table->string('age_recommendation', 50);
            $table->string('timing', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('heritage_village_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traditional_activities');
    }
};
