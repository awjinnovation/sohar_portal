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
        Schema::create('village_attractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('heritage_village_id')->constrained('heritage_villages')->onDelete('cascade');
            $table->string('name_en', 255);
            $table->string('name_ar', 255);
            $table->text('description_en');
            $table->text('description_ar');
            $table->text('location_description_en')->nullable();
            $table->text('location_description_ar')->nullable();
            $table->string('visiting_hours')->nullable();
            $table->text('accessibility_info_en')->nullable();
            $table->text('accessibility_info_ar')->nullable();
            $table->string('recommended_duration', 100)->nullable();
            $table->string('age_suitability', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('heritage_village_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('village_attractions');
    }
};
