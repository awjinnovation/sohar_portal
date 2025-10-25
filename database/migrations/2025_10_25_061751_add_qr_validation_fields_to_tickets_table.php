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
            // Only add columns that don't exist yet
            // ticket_number, quantity, booking_date, transaction_id already exist
            $table->string('validated_by')->nullable()->after('used_at');
            $table->integer('check_in_count')->default(0)->after('validated_by');
            $table->integer('max_check_ins')->default(1)->after('check_in_count');
            $table->string('validation_location')->nullable()->after('max_check_ins');
            $table->text('validation_notes')->nullable()->after('validation_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'validated_by',
                'check_in_count',
                'max_check_ins',
                'validation_location',
                'validation_notes'
            ]);
        });
    }
};
