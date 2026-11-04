<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitor satisfaction responses, «راضي» (docs/radi_satisfaction_contract.md §2).
 *
 * - score: overall rating 1–5; aspects: {key: 1–5} for the tenant's aspects.
 * - device_id: sha256 (with the app key) of the app's per-install id, never the raw value.
 * - response_date: the tenant-local day, for "one response per user/device
 *   per event per day"; dedupe_key (unique per tenant) closes the race.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satisfaction_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedTinyInteger('score');
            $table->json('aspects')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('contact_ok')->default(false);
            $table->string('locale', 5)->default('ar');
            $table->string('platform', 20)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('device_id', 64)->nullable();
            $table->date('response_date');
            $table->string('dedupe_key', 100);
            $table->timestamps();

            $table->unique(['tenant_id', 'dedupe_key']);
            $table->index(['tenant_id', 'response_date']);
            $table->index(['tenant_id', 'event_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_responses');
    }
};
