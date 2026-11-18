<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gate sales made with no connection and synced later (docs/gate_sales_contract.md §10):
 * card terminal sales with offline ticket codes, no buyer phone and no SMS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('box_office_orders', function (Blueprint $table) {
            $table->boolean('offline')->default(false)->after('payment_method');
            $table->timestamp('synced_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('box_office_orders', function (Blueprint $table) {
            $table->dropColumn(['offline', 'synced_at']);
        });
    }
};
