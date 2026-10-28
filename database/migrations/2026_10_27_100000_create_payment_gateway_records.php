<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gateway records (docs/payments_thawani.md §4): every checkout session,
 * every API call (with each retry), every webhook delivery, every payment
 * state change and every gateway refund. Plus the reconciliation schedule
 * on payments (next_check_at / check_count).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_session_id', 120)->nullable()->after('gateway');
            $table->string('gateway_payment_id', 120)->nullable()->after('gateway_session_id');
            $table->timestamp('next_check_at')->nullable()->after('refunded_at');
            $table->timestamp('last_checked_at')->nullable()->after('next_check_at');
            $table->unsignedInteger('check_count')->default(0)->after('last_checked_at');
            $table->boolean('needs_review')->default(false)->after('check_count');
            $table->string('review_reason', 120)->nullable()->after('needs_review');

            $table->index('gateway_session_id');
            $table->index(['status', 'method', 'next_check_at']);
            $table->index(['needs_review', 'tenant_id']);
        });

        Schema::create('payment_gateway_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('mode', 12);                       // uat | production
            $table->string('session_id', 120);
            $table->string('client_reference_id', 120);
            $table->string('invoice', 120)->nullable();
            $table->string('status', 20)->default('open');    // open | paid | cancelled | expired
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->text('checkout_url');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->json('data')->nullable();                 // last session object from the gateway
            $table->timestamps();

            $table->unique(['gateway', 'session_id']);
            $table->index(['payment_id', 'status']);
            $table->index(['status', 'gateway', 'last_checked_at']); // payments:reconcile late-capture sweep
        });

        Schema::create('payment_gateway_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->string('gateway', 20);
            $table->string('mode', 12)->nullable();
            $table->string('operation', 40);                  // create_session | get_session | ...
            $table->uuid('correlation_id')->index();          // same for every attempt of one operation
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->string('method', 8);
            $table->string('url', 500);
            $table->json('request_body')->nullable();         // secrets redacted
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->mediumText('response_body')->nullable();  // truncated
            $table->string('outcome', 20);                    // ok | client_error | server_error | network_error | circuit_open
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->string('gateway', 20);
            $table->string('event_type', 80)->nullable();
            $table->string('session_id', 120)->nullable()->index();
            $table->string('payload_hash', 64);
            $table->boolean('signature_valid')->nullable();   // null = no webhook secret configured
            $table->json('headers')->nullable();
            $table->mediumText('raw_body');
            $table->string('status', 20)->default('received'); // received | processed | ignored | failed | rejected
            $table->unsignedInteger('deliveries')->default(1);
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'payload_hash']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->string('source', 20);                     // api | redirect | webhook | reconciler | poll | admin | scheduler | system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'id']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('gateway_payment_id', 120)->nullable();
            $table->string('gateway_refund_id', 120)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('pending'); // pending | processing | succeeded | failed | needs_review
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('payment_id');                      // Thawani refunds in full, once
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_gateway_calls');
        Schema::dropIfExists('payment_gateway_sessions');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['gateway_session_id']);
            $table->dropIndex(['status', 'method', 'next_check_at']);
            $table->dropIndex(['needs_review', 'tenant_id']);
            $table->dropColumn(['gateway_session_id', 'gateway_payment_id', 'next_check_at', 'last_checked_at', 'check_count', 'needs_review', 'review_reason']);
        });
    }
};
