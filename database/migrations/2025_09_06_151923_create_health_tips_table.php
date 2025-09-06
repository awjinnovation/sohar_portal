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
        Schema::create('health_tips', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('title_ar', 255);
            $table->text('content');
            $table->text('content_ar');
            $table->string('category', 50);
            $table->string('icon', 50);
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_tips');
    }
};
