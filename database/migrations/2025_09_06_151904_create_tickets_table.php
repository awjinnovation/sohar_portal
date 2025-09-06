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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->enum('ticket_type', ['standard', 'vip', 'premium', 'early_bird']);
            $table->enum('status', ['active', 'used', 'expired', 'cancelled'])->default('active');
            $table->decimal('price', 10, 3);
            $table->char('currency', 3)->default('OMR');
            $table->string('holder_name', 255);
            $table->string('seat_number', 20)->nullable();
            $table->string('qr_code', 255)->unique();
            $table->dateTime('purchase_date');
            $table->dateTime('used_at')->nullable();
            $table->timestamps();
            
            $table->index('event_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('qr_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
