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
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('tickets', 'ticket_number')) {
                $table->string('ticket_number', 50)->unique()->after('id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'ticket_number')) {
                $table->dropColumn('ticket_number');
            }
        });
    }
};
