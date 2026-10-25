<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns too short for what the code stores. SQLite ignores VARCHAR
 * lengths; MySQL in strict mode rejects the write ("Data too long for
 * column"), so these paths only failed on MySQL:
 *
 * - otp_verifications.otp_code holds HMAC-SHA256(phone|code, APP_KEY) as 64
 *   hex characters (AuthController::hashOtp), not the 6-digit code it was
 *   created for: every OTP send failed.
 * - box_office_orders.status takes 'paid_unfulfilled' (16 characters, a late
 *   capture without seats) next to pending | paid | expired | cancelled |
 *   refunded: the fulfilment transaction failed.
 */
return new class extends Migration
{
    // SQLite column changes rebuild the table; foreign keys must be switched
    // off outside a transaction for that to be safe.
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->string('otp_code', 64)->change();
        });

        Schema::table('box_office_orders', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Narrowing would truncate stored values (OTP hashes, statuses), and
        // the wider columns are harmless: nothing to undo.
    }
};
