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
        Schema::create('map_locations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('name', 255);
            $table->string('name_ar', 255);
            $table->text('description');
            $table->text('description_ar');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('icon', 50);
            $table->unsignedInteger('color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('type');
            $table->index(['latitude', 'longitude']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_locations');
    }
};
