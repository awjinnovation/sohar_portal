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
        Schema::create('craft_demonstration_schedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demonstration_id')->constrained('craft_demonstrations')->onDelete('cascade');
            $table->time('schedule_time');
            $table->timestamps();
            
            $table->index('demonstration_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('craft_demonstration_schedule');
    }
};
