<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('name_ar', 100);
            $table->text('description');
            $table->text('description_ar');
            $table->string('icon_name', 50);
            $table->unsignedInteger('color_value');
            $table->string('image_url', 500)->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('display_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};