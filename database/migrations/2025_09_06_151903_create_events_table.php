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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('title_ar', 255);
            $table->text('description');
            $table->text('description_ar');
            $table->foreignId('category_id')->constrained();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('location', 255);
            $table->string('location_ar', 255)->nullable();
            $table->unsignedBigInteger('map_location_id')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->json('images')->nullable();
            $table->decimal('price', 10, 3)->default(0);
            $table->char('currency', 3)->default('OMR');
            $table->integer('available_tickets')->nullable();
            $table->integer('total_tickets')->nullable();
            $table->string('organizer_name', 255)->nullable();
            $table->string('organizer_name_ar', 255)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category_id');
            $table->index('start_time');
            $table->index('is_featured');
            $table->index('is_active');
            $table->index('map_location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
