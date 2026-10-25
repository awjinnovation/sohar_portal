<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrades spatie/laravel-permission tables created without "teams" to the
 * teams layout (team = tenant). Role definitions stay global (team_id NULL);
 * role/permission *assignments* become per tenant. Existing assignments are
 * attached to the north-batinah tenant.
 *
 * On a fresh install the original permission migration already creates the
 * teams layout (config('permission.teams') = true) and this is a no-op.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $tenantId = DB::table('tenants')->where('slug', 'north-batinah')->value('id');

        if (! Schema::hasColumn('roles', 'team_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->after('id');
                $table->index('team_id', 'roles_team_foreign_key_index');
                $table->dropUnique('roles_name_guard_name_unique');
                $table->unique(['team_id', 'name', 'guard_name']);
            });
        }

        foreach (['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'] as $tableName => $pivot) {
            if (Schema::hasColumn($tableName, 'team_id')) {
                continue;
            }

            $tmp = $tableName.'_teams';
            $parent = $pivot === 'role_id' ? 'roles' : 'permissions';

            Schema::create($tmp, function (Blueprint $table) use ($pivot, $parent, $tableName) {
                $table->unsignedBigInteger($pivot);
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->unsignedBigInteger('team_id');
                $table->foreign($pivot)->references('id')->on($parent)->onDelete('cascade');
                $table->primary(['team_id', $pivot, 'model_id', 'model_type'], $tableName.'_'.($pivot === 'role_id' ? 'role' : 'permission').'_model_type_primary_t');
            });

            DB::table($tableName)->orderBy($pivot)->get()->each(function ($row) use ($tmp, $pivot, $tenantId) {
                DB::table($tmp)->insert([
                    $pivot => $row->{$pivot},
                    'model_type' => $row->model_type,
                    'model_id' => $row->model_id,
                    'team_id' => $tenantId,
                ]);
            });

            Schema::drop($tableName);
            Schema::rename($tmp, $tableName);

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->index(['model_id', 'model_type'], $tableName.'_model_id_model_type_index');
                $table->index('team_id', $tableName.'_team_foreign_key_index');
            });
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Irreversible in practice (assignments of several tenants cannot be
        // collapsed into one); intentionally left as a no-op.
    }
};
