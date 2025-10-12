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
        Schema::table('tickets', function (Blueprint $table) {
            $table->date('booking_date')->after('event_id')->nullable();
            $table->integer('quantity')->after('booking_date')->default(1);
            $table->index(['event_id', 'booking_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'booking_date']);
            $table->dropColumn(['booking_date', 'quantity']);
        });
    }
};
