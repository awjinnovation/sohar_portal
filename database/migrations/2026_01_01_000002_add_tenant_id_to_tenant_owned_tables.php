<?php

use App\Tenancy\LegacySettingsMapper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introduces tenant_id on every tenant-owned table, creates the
 * `north-batinah` tenant (the pre-platform data owner) and backfills all
 * existing rows to it. Legacy app_settings are folded into the tenant's
 * settings document.
 */
return new class extends Migration
{
    public const TABLES = [
        'categories',
        'events',
        'event_tags',
        'tickets',
        'payments',
        'restaurants',
        'map_locations',
        'announcements',
        'notifications',
        'app_settings',
        'privacy_policies',
        'terms_and_conditions',
        'user_favorites',
        'user_interests',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                // Nullable at the DB level so the column can be added to
                // populated tables on every driver; the application always
                // fills it (BelongsToTenant) and the backfill below covers
                // historical rows. No FK constraint on purpose: adding one on
                // SQLite rebuilds the table, which would cascade-delete child
                // rows. Tenants are never hard-deleted (status=inactive).
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->index('tenant_id');
            });
        }

        // app_settings keys become unique per tenant instead of globally.
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropUnique('app_settings_setting_key_unique');
            $table->unique(['tenant_id', 'setting_key']);
        });

        $tenantId = $this->createNorthBatinahTenant();

        foreach (self::TABLES as $tableName) {
            if (Schema::hasTable($tableName)) {
                DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            }
        }
    }

    private function createNorthBatinahTenant(): int
    {
        $existing = DB::table('tenants')->where('slug', 'north-batinah')->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $legacy = [];
        // Only public rows: everything folded here becomes publicly served.
        foreach (DB::table('app_settings')->where('is_public', true)->get() as $row) {
            $legacy[$row->setting_key] = match ($row->setting_type) {
                'integer' => (int) $row->setting_value,
                'boolean' => filter_var($row->setting_value, FILTER_VALIDATE_BOOLEAN),
                'decimal' => (float) $row->setting_value,
                'json' => json_decode($row->setting_value, true),
                default => $row->setting_value,
            };
        }

        $mapped = LegacySettingsMapper::toTenant($legacy);
        $name = array_merge(
            ['en' => 'North Batinah Events', 'ar' => 'فعاليات شمال الباطنة'],
            array_filter($mapped['name'])
        );

        return (int) DB::table('tenants')->insertGetId([
            'slug' => 'north-batinah',
            'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
            'status' => 'active',
            'settings' => json_encode($mapped['settings'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'setting_key']);
        });

        foreach (array_reverse(self::TABLES) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }

        Schema::table('app_settings', function (Blueprint $table) {
            $table->unique('setting_key');
        });

        DB::table('tenants')->where('slug', 'north-batinah')->delete();
    }
};
