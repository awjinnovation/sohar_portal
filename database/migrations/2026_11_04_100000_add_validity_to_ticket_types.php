<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket validity per ticket type (docs/ticket_types_contract.md §7):
 * `date` (one chosen day, the behaviour so far), `any_day` (one visit on any
 * day the event runs) or `all_days` (every day the event runs, `admits`
 * entries per gate day).
 *
 * The value is copied to the ticket (and to the gate order line) when it is
 * sold, so a later edit of the type never changes issued tickets. Existing
 * rows keep `date`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_ticket_types', function (Blueprint $table) {
            $table->string('validity', 10)->default('date')->after('admits'); // date | any_day | all_days
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('validity', 10)->default('date')->after('ticket_type_id');
        });
        Schema::table('box_office_order_lines', function (Blueprint $table) {
            $table->string('validity', 10)->default('date')->after('admits');
        });
    }

    public function down(): void
    {
        Schema::table('box_office_order_lines', function (Blueprint $table) {
            $table->dropColumn('validity');
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('validity');
        });
        Schema::table('event_ticket_types', function (Blueprint $table) {
            $table->dropColumn('validity');
        });
    }
};
