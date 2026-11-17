<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card terminal payments without a receipt photo (docs/gate_sales_contract.md
 * §9): when the photo is optional (`gate_sales.pos_receipt_photo_required`
 * off), staff may confirm the payment by hand, e.g. in poor light. Such a
 * receipt row has no file (`source = manual`), only what the staff typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('box_office_receipts', function (Blueprint $table) {
            $table->string('disk', 20)->nullable()->change();
            $table->string('path', 255)->nullable()->change();
            $table->char('sha256', 64)->nullable()->change();
            $table->string('mime', 40)->nullable()->change();
            $table->unsignedInteger('size')->nullable()->change();
            $table->string('source', 8)->default('photo')->after('idempotency_key'); // photo | manual
            $table->string('note', 200)->nullable()->after('source');               // staff note (manual confirmation)
        });
    }

    public function down(): void
    {
        Schema::table('box_office_receipts', function (Blueprint $table) {
            $table->dropColumn(['source', 'note']);
        });
    }
};
