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
        Schema::create('craft_demonstrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('heritage_village_id')->constrained('heritage_villages')->onDelete('cascade');
            $table->string('craft_name_en', 255);
            $table->string('craft_name_ar', 255);
            $table->text('description_en');
            $table->text('description_ar');
            $table->string('artisan_name', 255)->nullable();
            $table->text('demonstration_times')->nullable();
            $table->text('materials_used_en')->nullable();
            $table->text('materials_used_ar')->nullable();
            $table->text('historical_significance_en')->nullable();
            $table->text('historical_significance_ar')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->boolean('can_try_hands_on')->default(false);
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
        Schema::dropIfExists('craft_demonstrations');
    }
};
