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
        Schema::create('cultural_timeline_events', function (Blueprint $table) {
            $table->id();
            $table->string('year', 20);
            $table->string('title_en', 255);
            $table->string('title_ar', 255);
            $table->text('description_en');
            $table->text('description_ar');
            $table->string('image_url', 500);
            $table->string('category', 50);
            $table->boolean('is_key_milestone')->default(false);
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('year');
            $table->index('category');
            $table->index('is_key_milestone');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cultural_timeline_events');
    }
};
