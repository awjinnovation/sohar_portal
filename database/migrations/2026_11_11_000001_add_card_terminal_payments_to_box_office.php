<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gate box office: card terminal payments (docs/gate_sales_contract.md §9).
 * Staff charge the visitor's card on the organizer's own POS terminal and
 * photograph the receipt; the server reads it (AI), checks it against the
 * order and against every receipt already accepted, then issues the tickets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('box_office_orders', function (Blueprint $table) {
            $table->string('payment_method', 8)->default('link')->after('status'); // link | pos
            $table->boolean('needs_review')->default(false)->after('payment_method');
            $table->index(['tenant_id', 'needs_review']);
        });

        Schema::create('box_office_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('box_office_orders')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('gate_devices')->nullOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 100);
            $table->string('disk', 20);
            $table->string('path', 255);
            $table->char('sha256', 64);
            $table->string('mime', 40);
            $table->unsignedInteger('size');
            $table->string('verdict', 10);                 // verified | review | rejected
            $table->json('reasons')->nullable();           // [{code, data?}]
            $table->string('ai_status', 12);               // ok | unavailable | budget
            $table->string('ai_model', 60)->nullable();
            $table->json('extracted')->nullable();         // the fields as read
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->date('receipt_date')->nullable();
            $table->string('terminal_id', 40)->nullable();
            $table->string('approval_code', 20)->nullable();
            $table->string('rrn', 30)->nullable();
            $table->char('card_last4', 4)->nullable();
            // Set only on accepted receipts: a second order can never use the same one.
            $table->boolean('accepted')->default(false);
            $table->string('rrn_key', 60)->nullable();
            $table->string('auth_key', 120)->nullable();
            $table->unsignedBigInteger('duplicate_of_order_id')->nullable();
            $table->string('decision', 12)->nullable();    // approved | cleared | voided (admin)
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'idempotency_key']);
            $table->unique(['tenant_id', 'rrn_key']);
            $table->unique(['tenant_id', 'auth_key']);
            $table->index(['tenant_id', 'sha256']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('box_office_receipts');
        Schema::table('box_office_orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'needs_review']);
            $table->dropColumn(['payment_method', 'needs_review']);
        });
    }
};
