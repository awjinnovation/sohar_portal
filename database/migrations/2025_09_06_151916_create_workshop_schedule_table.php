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
        Schema::create('workshop_schedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('cultural_workshops')->onDelete('cascade');
            $table->time('schedule_time');
            $table->timestamps();
            
            $table->index('workshop_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_schedule');
    }
};
