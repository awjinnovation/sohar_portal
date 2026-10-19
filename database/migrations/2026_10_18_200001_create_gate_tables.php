<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gate scanner (docs/ai_and_gate_contract.md §B1): gates, paired scanner
 * devices, short-lived pairing codes and the scan log. All tenant-owned.
 *
 * gate_scans keeps exactly the contract columns (read by the AI insights
 * service); gate_id / device_id are nullable so scans made through the
 * legacy staff screen (/tickets/validate, /tickets/{id}/mark-used) are
 * logged too. direction is `in` | `out`; instants are stored in UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->json('name');                                   // {en, ar}
            $table->string('code', 16);                             // e.g. "A"
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete(); // null = all events
            $table->string('direction', 8)->default('entry');       // entry | exit | both
            $table->boolean('allow_reentry')->default(false);
            $table->json('ticket_types')->nullable();               // null = every ticket type
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('gate_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('device_type', 20)->default('phone');   // android_pos|handheld|phone|tablet|web
            $table->string('model')->nullable();
            $table->string('platform', 40)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('scanner_source', 20)->nullable();      // last used: camera|hardware|wedge|manual
            $table->string('status', 10)->default('active');       // active | revoked
            $table->unsignedTinyInteger('battery')->nullable();    // percent, from heartbeat
            $table->unsignedInteger('queue_size')->default(0);     // offline scans waiting to sync
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('staff_login_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('gate_pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gate_id')->constrained()->cascadeOnDelete();
            $table->string('code', 6)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('device_id')->nullable()->constrained('gate_devices')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gate_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('gate_devices')->nullOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code_hash', 64);
            $table->string('result', 20);
            $table->string('direction', 4)->default('in');         // in | out
            $table->string('source', 10)->default('camera');       // camera|hardware|wedge|manual
            $table->timestamp('scanned_at');                        // device time (UTC)
            $table->timestamp('received_at');
            $table->boolean('offline')->default(false);
            $table->string('idempotency_key', 100)->unique();
            $table->boolean('conflict')->default(false);

            $table->index(['tenant_id', 'scanned_at']);
            $table->index(['tenant_id', 'gate_id', 'scanned_at']);
            $table->index(['ticket_id', 'result']);
            $table->index(['tenant_id', 'conflict']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_scans');
        Schema::dropIfExists('gate_pairing_codes');
        Schema::dropIfExists('gate_devices');
        Schema::dropIfExists('gates');
    }
};
