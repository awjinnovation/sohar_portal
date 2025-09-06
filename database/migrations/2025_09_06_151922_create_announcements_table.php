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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('title_ar', 255);
            $table->text('content');
            $table->text('content_ar');
            $table->enum('type', ['info', 'warning', 'emergency', 'celebration'])->default('info');
            $table->integer('priority')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_active')->default(true);
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            
            $table->index('type');
            $table->index('priority');
            $table->index('is_pinned');
            $table->index('is_active');
            $table->index(['start_datetime', 'end_datetime']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
