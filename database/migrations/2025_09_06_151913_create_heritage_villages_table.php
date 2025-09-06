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
        Schema::create('heritage_villages', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 255);
            $table->string('name_ar', 255);
            $table->text('description_en');
            $table->text('description_ar');
            $table->enum('type', ['maritime', 'agricultural', 'bedouin']);
            $table->string('cover_image', 500);
            $table->string('opening_hours', 100);
            $table->string('virtual_tour_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heritage_villages');
    }
};
