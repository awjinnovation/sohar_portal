<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket types per event (docs/ticket_types_contract.md §1).
 *
 * - event_ticket_types: price, people admitted per ticket, own capacity,
 *   per-order limit, ages, ID check, sales window, visibility, colour, sort.
 * - tickets.ticket_type_id (+ ticket_type widened from the old enum to the
 *   type key). Multi-entry uses the existing max_check_ins / check_in_count
 *   columns, exposed as `admits` / `entries_used`.
 * - box_office_order_lines: one row per ticket type of a gate order.
 *
 * Backfill: every event gets a `general` type at its current price (plus one
 * type per extra legacy `pricing` tier); existing tickets are linked to the
 * type of their key (`standard` → `general`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->json('name');                                   // {en, ar}
            $table->json('description')->nullable();               // {en, ar}
            $table->string('category', 16)->default('general');    // general|family|vip|child|senior|student|group|other
            $table->unsignedBigInteger('price_minor')->default(0); // 0 = free
            $table->unsignedSmallInteger('admits')->default(1);    // people per ticket
            $table->unsignedInteger('capacity')->nullable();       // per day; null = shares the event capacity
            $table->unsignedSmallInteger('per_order_max')->default(10);
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->boolean('requires_id')->default(false);
            $table->dateTime('sales_start')->nullable();            // tenant wall-clock, like events.start_time
            $table->dateTime('sales_end')->nullable();
            $table->string('visibility', 12)->default('public');   // public | box_office
            $table->string('color', 9)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'key']);
            $table->index(['tenant_id', 'event_id', 'is_active']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('ticket_type_id')->nullable()->after('ticket_type')->constrained('event_ticket_types')->nullOnDelete();
        });
        // The old enum (standard|vip|premium|early_bird) cannot hold type keys.
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('ticket_type', 40)->default('general')->change();
        });

        Schema::create('box_office_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('box_office_orders')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained('event_ticket_types')->nullOnDelete();
            $table->string('key', 40);
            $table->json('name');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->unsignedSmallInteger('admits')->default(1);
            $table->timestamps();

            $table->index(['ticket_type_id', 'order_id']);
        });

        $this->backfill();
    }

    private function backfill(): void
    {
        $decimals = [];
        foreach (DB::table('tenants')->get(['id', 'settings']) as $t) {
            $s = json_decode((string) $t->settings, true) ?: [];
            $decimals[$t->id] = (int) data_get($s, 'tenant.currency_decimals', 3);
        }
        $labels = [
            'vip' => ['VIP', 'كبار الشخصيات', 'vip'],
            'premium' => ['Premium', 'مميزة', 'other'],
            'early_bird' => ['Early bird', 'حجز مبكر', 'other'],
        ];
        $now = now();

        foreach (DB::table('events')->orderBy('id')->get(['id', 'tenant_id', 'price', 'pricing', 'is_active']) as $event) {
            if (! $event->tenant_id || DB::table('event_ticket_types')->where('event_id', $event->id)->exists()) {
                continue;
            }
            $dec = $decimals[$event->tenant_id] ?? 3;
            $pricing = json_decode((string) $event->pricing, true) ?: [];
            $standard = collect($pricing)->first(fn ($p) => in_array($p['type'] ?? null, ['standard', 'general'], true));
            $base = \App\Support\Money::toMinor((string) ($standard['price'] ?? $event->price ?? 0), $dec);

            $rows = [[
                'key' => 'general', 'name' => ['en' => 'General admission', 'ar' => 'تذكرة عامة'], 'category' => 'general',
                'price_minor' => $base, 'sort' => 0,
            ]];
            foreach ($pricing as $i => $tier) {
                $key = (string) ($tier['type'] ?? '');
                if ($key === '' || in_array($key, ['standard', 'general'], true) || collect($rows)->contains('key', $key)) {
                    continue;
                }
                [$en, $ar, $cat] = $labels[$key] ?? [ucfirst(str_replace('_', ' ', $key)), $key, 'other'];
                $rows[] = ['key' => $key, 'name' => ['en' => $en, 'ar' => $ar], 'category' => $cat,
                    'price_minor' => \App\Support\Money::toMinor((string) ($tier['price'] ?? 0), $dec), 'sort' => $i + 1];
            }
            foreach ($rows as $row) {
                DB::table('event_ticket_types')->insert([
                    'tenant_id' => $event->tenant_id, 'event_id' => $event->id, 'key' => $row['key'],
                    'name' => json_encode($row['name'], JSON_UNESCAPED_UNICODE), 'category' => $row['category'],
                    'price_minor' => $row['price_minor'], 'admits' => 1, 'per_order_max' => 10, 'visibility' => 'public',
                    'sort' => $row['sort'], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $types = DB::table('event_ticket_types')->where('event_id', $event->id)->pluck('id', 'key');
            foreach (DB::table('tickets')->where('event_id', $event->id)->whereNull('ticket_type_id')->distinct()->pluck('ticket_type') as $old) {
                $key = in_array($old, ['standard', 'general', null, ''], true) || ! isset($types[$old]) ? 'general' : $old;
                DB::table('tickets')->where('event_id', $event->id)->whereNull('ticket_type_id')->where('ticket_type', $old)
                    ->update(['ticket_type_id' => $types[$key], 'ticket_type' => $key]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('box_office_order_lines');
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_type_id');
        });
        Schema::dropIfExists('event_ticket_types');
    }
};
