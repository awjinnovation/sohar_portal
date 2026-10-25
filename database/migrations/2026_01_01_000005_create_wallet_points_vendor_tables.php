<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->json('name');                 // {"en","ar"}
            $table->json('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('category')->nullable(); // food, merchandise, services...
            $table->unsignedBigInteger('restaurant_id')->nullable(); // optional link to a listing
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vendor_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('staff'); // staff | manager
            $table->timestamps();
            $table->unique(['vendor_id', 'user_id']);
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('OMR');
            // balance = cash + promo. held = funds reserved by deposits.
            $table->bigInteger('balance_minor')->default(0);
            $table->bigInteger('cash_minor')->default(0);
            $table->bigInteger('promo_minor')->default(0);
            $table->bigInteger('held_minor')->default(0);
            $table->string('status')->default('active'); // active | frozen
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        // Append-only ledger: amounts are never updated; only `status`
        // changes (pending -> completed / reversed) for refund payouts.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type');       // topup, ticket_purchase, vendor_payment, deposit_hold, ...
            $table->string('direction');  // credit | debit
            $table->unsignedBigInteger('amount_minor');
            $table->bigInteger('cash_delta_minor')->default(0);
            $table->bigInteger('promo_delta_minor')->default(0);
            $table->bigInteger('held_delta_minor')->default(0);
            $table->bigInteger('balance_after_minor');
            $table->bigInteger('cash_after_minor');
            $table->bigInteger('promo_after_minor');
            $table->bigInteger('held_after_minor');
            $table->string('status')->default('completed'); // completed | pending | reversed
            $table->nullableMorphs('reference');
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable(); // staff/admin actor
            $table->string('idempotency_key')->nullable();
            $table->string('title_key')->nullable();
            $table->string('description_key')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['wallet_id', 'idempotency_key']);
            $table->index(['wallet_id', 'id']);
        });

        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('bonus_minor')->default(0);
            $table->string('status')->default('pending'); // pending | completed | failed | cancelled
            $table->string('idempotency_key')->nullable();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['wallet_id', 'idempotency_key']);
        });

        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('type');   // wristband | cup | locker | equipment | other
            $table->string('label')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status')->default('held'); // held | released | forfeited
            $table->string('idempotency_key')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('released_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['wallet_id', 'idempotency_key']);
        });

        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->string('method'); // original_payment | bank_transfer
            $table->string('status')->default('pending'); // pending | approved | rejected | paid
            $table->text('reason')->nullable();
            $table->string('bank_iban')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->unsignedBigInteger('hold_transaction_id')->nullable();
            $table->unsignedBigInteger('reversal_transaction_id')->nullable();
            $table->timestamps();
            $table->unique(['wallet_id', 'idempotency_key']);
        });

        Schema::create('points_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('lifetime_earned')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('points_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('points_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type');              // earn | redeem | expire | adjust
            $table->bigInteger('points');        // signed
            $table->bigInteger('balance_after');
            $table->bigInteger('remaining')->default(0); // unspent points of an earn lot (FIFO)
            $table->timestamp('expires_at')->nullable();
            $table->nullableMorphs('reference');
            $table->string('idempotency_key')->nullable();
            $table->string('title_key')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['points_account_id', 'idempotency_key']);
            $table->index(['points_account_id', 'expires_at']);
        });

        Schema::create('pay_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('jti', 64)->unique();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('wallet_id');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('used_by')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'pay_tokens', 'points_transactions', 'points_accounts', 'refund_requests',
            'deposits', 'wallet_topups', 'wallet_transactions', 'wallets', 'vendor_user', 'vendors'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
