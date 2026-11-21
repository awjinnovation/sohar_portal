<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Minimum tickets of a type in one order (MSR-402); per_order_max is the maximum. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_ticket_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('per_order_min')->default(1)->after('per_order_max');
        });
    }

    public function down(): void
    {
        Schema::table('event_ticket_types', function (Blueprint $table) {
            $table->dropColumn('per_order_min');
        });
    }
};
