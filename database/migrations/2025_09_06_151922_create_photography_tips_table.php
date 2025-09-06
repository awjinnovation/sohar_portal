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
        Schema::create('photography_tips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_spot_id')->constrained('photo_spots')->onDelete('cascade');
            $table->text('tip');
            $table->integer('display_order')->default(0);
            $table->timestamps();
            
            $table->index('photo_spot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photography_tips');
    }
};
