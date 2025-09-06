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
            $table->foreignId('village_id')->constrained('heritage_villages')->onDelete('cascade');
            $table->string('attraction_en', 255);
            $table->string('attraction_ar', 255);
            $table->integer('display_order')->default(0);
            $table->timestamps();
            
            $table->index('village_id');
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
