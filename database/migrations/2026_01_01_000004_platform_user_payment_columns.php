<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite column changes rebuild the table; foreign keys must be switched
    // off outside a transaction for that to be safe.
    public $withinTransaction = false;

    public function up(): void
    {
        // --- users: global identities ------------------------------------
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_super_admin')) {
                $table->boolean('is_super_admin')->default(false);
            }
        });

        if (Schema::hasColumn('users', 'thawani_customer_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('thawani_customer_id'));
        }

        // Phone-only (OTP) users no longer get a fabricated e-mail address.
        Schema::disableForeignKeyConstraints();
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
        Schema::enableForeignKeyConstraints();

        // Platform super-admins: whoever held the global super-admin role.
        $superRoleId = DB::table('roles')->where('name', 'super-admin')->value('id');
        if ($superRoleId) {
            $ids = DB::table('model_has_roles')->where('role_id', $superRoleId)->pluck('model_id');
            DB::table('users')->whereIn('id', $ids)->update(['is_super_admin' => true]);
        }

        // --- payments ---------------------------------------------------
        foreach (['thawani_session_id', 'thawani_response'] as $col) {
            if (Schema::hasColumn('payments', $col)) {
                Schema::table('payments', fn (Blueprint $table) => $table->dropColumn($col));
            }
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('amount_minor')->nullable()->after('amount');
            $table->string('method')->default('gateway')->after('payment_type'); // gateway | wallet
            $table->string('gateway')->nullable()->after('method');              // bankmuscat | fake
            $table->string('idempotency_key')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->unique(['tenant_id', 'user_id', 'idempotency_key']);
        });

        // Backfill amount_minor for historical payments (OMR, 3 decimals).
        DB::table('payments')->whereNull('amount_minor')->orderBy('id')->each(function ($p) {
            DB::table('payments')->where('id', $p->id)->update([
                'amount_minor' => (int) round(((float) $p->amount) * 1000),
            ]);
        });

        // Payments that already produced tickets are fulfilled.
        $fulfilled = DB::table('tickets')->whereNotNull('transaction_id')->distinct()->pluck('transaction_id');
        DB::table('payments')->whereIn('transaction_id', $fulfilled)->update(['fulfilled_at' => now()]);

        // --- events: optional refundable deposit per ticket -------------
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('deposit_minor')->default(0);
            $table->string('deposit_type')->nullable(); // wristband | cup | locker | equipment | other
        });

        // --- notifications: public broadcast flag (was queried but missing)
        if (! Schema::hasColumn('notifications', 'is_public')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->boolean('is_public')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::table('notifications', fn (Blueprint $table) => $table->dropColumn('is_public'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['deposit_minor', 'deposit_type']));

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'user_id', 'idempotency_key']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['amount_minor', 'method', 'gateway', 'idempotency_key', 'fulfilled_at', 'refunded_at']);
            $table->string('thawani_session_id')->nullable();
            $table->json('thawani_response')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
            $table->string('thawani_customer_id')->nullable();
        });
    }
};
