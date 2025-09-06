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
        Schema::create('first_aid_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('name_ar', 255);
            $table->string('location', 255);
            $table->string('location_ar', 255);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('operating_hours', 100);
            $table->string('contact_number', 20)->nullable();
            $table->text('services_available')->nullable();
            $table->text('services_available_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['latitude', 'longitude']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('first_aid_stations');
    }
};
