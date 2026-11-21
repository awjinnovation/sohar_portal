<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GET /gate/manifest/changes reads the tickets changed since a device's last
 * call (docs/gate_signed_tickets.md §4): every 20 s per online gate device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('updated_at', 'tickets_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_updated_at_index');
        });
    }
};
