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
        Schema::create('ticket_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->enum('ticket_type', ['standard', 'vip', 'premium', 'early_bird']);
            $table->decimal('price', 10, 3);
            $table->integer('available_quantity');
            $table->text('benefits')->nullable();
            $table->text('benefits_ar')->nullable();
            $table->timestamps();
            
            $table->unique(['event_id', 'ticket_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_pricing');
    }
};
