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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('bank_muscat_order_id')->nullable()->after('transaction_id');
            $table->string('bank_muscat_tid')->nullable()->after('bank_muscat_order_id');
            $table->json('bank_muscat_response')->nullable()->after('thawani_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['bank_muscat_order_id', 'bank_muscat_tid', 'bank_muscat_response']);
        });
    }
};
