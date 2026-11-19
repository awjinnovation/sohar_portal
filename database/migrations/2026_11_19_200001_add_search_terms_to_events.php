<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Other names and search words for an event (comma separated), typed by the
 * organizer: the booking site shows them under the event and gives them to
 * search engines, so the page is found by the words people search with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('search_terms')->nullable()->after('description_ar');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('search_terms');
        });
    }
};
