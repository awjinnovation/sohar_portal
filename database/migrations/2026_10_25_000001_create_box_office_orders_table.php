<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gate box office (docs/gate_sales_contract.md §1–2): orders sold by gate
 * staff and paid by the visitor through a public payment link, plus the
 * `gates.can_sell` switch.
 *
 * A pending order holds its seats until it expires or is cancelled
 * (Event::getAvailableTicketsForDate counts pending, unexpired orders).
 * Money is in minor units; instants are UTC; booking_date is the gate day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('box_office_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('gate_devices')->nullOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 24);                       // BO-YYMMDD-NNNN, unique per tenant
            $table->string('buyer_phone', 20);                     // country code + local number, digits only
            $table->string('buyer_name', 120)->nullable();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->date('booking_date');
            $table->string('ticket_type', 40)->default('standard');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('deposit_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('currency', 3);
            $table->string('status', 12)->default('pending');      // pending | paid | expired | cancelled
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('public_token', 64)->unique();
            $table->string('locale', 2)->default('ar');
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->unsignedTinyInteger('sms_count')->default(0);
            $table->string('idempotency_key', 100);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
            $table->unique(['device_id', 'idempotency_key']);
            $table->index(['tenant_id', 'status', 'expires_at']);
            $table->index(['event_id', 'booking_date', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::table('gates', function (Blueprint $table) {
            $table->boolean('can_sell')->default(false)->after('allow_reentry');
        });

        // Same defaults as a fresh seed (GateSeeder) for already-seeded databases.
        $seeded = ['north-batinah' => ['A', 'B'], 'demo' => ['H1']];
        foreach ($seeded as $slug => $codes) {
            $tenantId = DB::table('tenants')->where('slug', $slug)->value('id');
            if ($tenantId) {
                DB::table('gates')->where('tenant_id', $tenantId)->whereIn('code', $codes)->update(['can_sell' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('box_office_orders');
        Schema::table('gates', function (Blueprint $table) {
            $table->dropColumn('can_sell');
        });
    }
};
