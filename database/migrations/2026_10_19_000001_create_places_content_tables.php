<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Places to visit + content (docs/places_content_contract.md §2). All
 * tenant-owned. Localized fields are JSON {en, ar}; rich-text bodies are
 * stored already sanitized (App\Support\HtmlSanitizer). Images live in
 * spatie medialibrary (`media` table): places cover/gallery, items
 * image/gallery, pages cover.
 *
 * Operational map pins (map_locations) are not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);                       // slug, unique per tenant
            $table->json('name');                            // {en, ar}
            $table->string('icon', 64)->default('place');    // Material Symbols Rounded name
            $table->string('color', 7)->default('#2F8E80');  // #RRGGBB
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('place_categories')->nullOnDelete();
            $table->string('slug', 120);
            $table->json('name');
            $table->json('summary')->nullable();             // ≤ 200 chars per language
            $table->json('description')->nullable();         // sanitized HTML per language
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->json('address')->nullable();
            $table->json('wilayat')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->json('link_label')->nullable();
            $table->string('phone', 40)->nullable();
            $table->json('opening_hours')->nullable();       // free text per language
            $table->unsignedInteger('entry_fee_minor')->nullable(); // null = unknown, 0 = free
            $table->string('icon', 64)->nullable();          // overrides the category icon
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->string('status', 12)->default('draft');  // draft | published
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'sort']);
        });

        Schema::create('event_place', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->primary(['event_id', 'place_id']);
        });

        Schema::create('content_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('icon', 64)->nullable();
            $table->string('layout', 10)->default('list');   // list | grid | logos | cards
            $table->boolean('show_in_discover')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('content_collections')->cascadeOnDelete();
            $table->string('slug', 120);
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->json('body')->nullable();
            $table->string('icon', 64)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->json('link_label')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('tags')->nullable();
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->string('status', 12)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['collection_id', 'slug']);
            $table->index(['tenant_id', 'collection_id', 'status', 'sort']);
        });

        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 120);
            $table->json('title');
            $table->json('body')->nullable();
            $table->string('icon', 64)->nullable();
            $table->boolean('show_in_more')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->string('status', 12)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('content_collections');
        Schema::dropIfExists('event_place');
        Schema::dropIfExists('places');
        Schema::dropIfExists('place_categories');
    }
};
