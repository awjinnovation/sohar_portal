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
        Schema::create('village_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('heritage_village_id')->constrained('heritage_villages')->onDelete('cascade');
            $table->string('image_url', 500);
            $table->string('caption_en')->nullable();
            $table->string('caption_ar')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            
            $table->index('heritage_village_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('village_images');
    }
};
