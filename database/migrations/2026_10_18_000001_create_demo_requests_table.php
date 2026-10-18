<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level (not tenant-scoped) leads from the public landing page's
 * "Book a demo" form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('organization', 160);
            $table->string('event_type', 32)->nullable();
            $table->string('expected_visitors', 16)->nullable();
            $table->string('season', 120)->nullable();
            $table->string('contact', 190);
            $table->string('contact_email', 190)->nullable();
            $table->text('notes')->nullable();
            $table->string('locale', 2)->default('en');
            $table->string('ip_hash', 64)->nullable()->index();
            $table->string('user_agent', 255)->nullable();
            $table->string('status', 16)->default('new')->index();
            $table->timestamp('mailed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
