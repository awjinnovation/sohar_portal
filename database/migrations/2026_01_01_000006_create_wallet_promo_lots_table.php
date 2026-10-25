<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promo credit lots (FIFO) so promo balance can expire
 * (tenant setting wallet.promo_expiry_days). The ledger stays append-only;
 * only `remaining_minor` here changes as promo is spent or expires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_promo_lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('wallet_transaction_id')->index(); // the promo credit
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('remaining_minor');
            $table->timestamp('expires_at')->nullable(); // null = never
            $table->timestamps();
            $table->index(['wallet_id', 'remaining_minor']);
            $table->index(['expires_at', 'remaining_minor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_promo_lots');
    }
};
