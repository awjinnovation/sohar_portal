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
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('service_name', 255);
            $table->string('service_name_ar', 255);
            $table->string('phone_number', 20);
            $table->string('secondary_phone', 20)->nullable();
            $table->enum('type', ['police', 'ambulance', 'fire', 'first_aid', 'security', 'other']);
            $table->string('location', 255)->nullable();
            $table->string('location_ar', 255)->nullable();
            $table->boolean('is_24_hours')->default(true);
            $table->integer('display_order')->default(0);
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
        Schema::dropIfExists('emergency_contacts');
    }
};
