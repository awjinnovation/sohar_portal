<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The operations room (Admin → Operations room) counts the tickets issued in
 * the current operating day, the last 15 and 60 minutes and every 5 minutes,
 * every few seconds: an index on (tenant_id, created_at) keeps that to the
 * day's rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'tickets_tenant_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_tenant_created_index');
        });
    }
};
