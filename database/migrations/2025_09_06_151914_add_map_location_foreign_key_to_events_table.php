<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only add foreign key for MySQL, skip for SQLite
        if (config('database.default') !== 'sqlite') {
            Schema::table('events', function (Blueprint $table) {
                $table->foreign('map_location_id')
                      ->references('id')
                      ->on('map_locations')
                      ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['map_location_id']);
        });
    }
};
