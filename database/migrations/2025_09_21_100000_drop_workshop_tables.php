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
        // Drop workshop-related tables
        Schema::dropIfExists('workshop_schedules');
        Schema::dropIfExists('workshop_registrations');
        Schema::dropIfExists('cultural_workshops');
        Schema::dropIfExists('craft_demonstration_schedules');
        Schema::dropIfExists('photography_tips');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible
    }
};