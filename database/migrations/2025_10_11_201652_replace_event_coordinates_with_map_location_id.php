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
        Schema::table('events', function (Blueprint $table) {
            // Drop the index on latitude and longitude first
            $table->dropIndex(['latitude', 'longitude']);

            // Drop the old columns
            $table->dropColumn(['latitude', 'longitude']);

            // Add the foreign key to map_locations
            $table->unsignedBigInteger('map_location_id')->nullable()->after('location_ar');
        });

        // Add foreign key constraint in separate statement to avoid issues
        Schema::table('events', function (Blueprint $table) {
            $table->foreign('map_location_id')
                  ->references('id')
                  ->on('map_locations')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Drop the foreign key
            $table->dropForeign(['map_location_id']);
            $table->dropColumn('map_location_id');

            // Restore the old columns
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Restore the index
            $table->index(['latitude', 'longitude']);
        });
    }
};
