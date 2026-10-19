<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Experience (docs/ai_and_gate_contract.md §A).
 *
 * - ai_requests: one row per OpenAI HTTP call (usage / cost accounting).
 *   Never stores prompts or answers.
 * - ai_conversations / ai_messages: visitor concierge history (30-day expiry,
 *   `ai:prune`). Guests are stored as sha256(X-Ai-Guest) in `guest_key`.
 * - ai_plans: "plan my visit" results, shareable by uuid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('feature', 40);            // chat, plan, copilot.*, briefing, ops_insights
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('reasoning_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status', 20);             // ok | error | rate_limited | budget_exceeded
            $table->string('error_code', 60)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('guest_key', 64)->nullable();   // sha256 of the X-Ai-Guest header
            $table->string('locale', 5)->default('ar');
            $table->string('title', 120)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'guest_key']);
            $table->index('updated_at');
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->uuid('conversation_id');
            $table->string('role', 16);               // user | assistant
            $table->text('content');
            $table->json('cards')->nullable();
            $table->json('suggestions')->nullable();
            $table->unsignedInteger('tokens')->default(0);
            $table->string('feedback_rating', 8)->nullable(); // up | down
            $table->string('feedback_comment', 1000)->nullable();
            $table->timestamp('feedback_at')->nullable();
            $table->timestamps();
            $table->foreign('conversation_id')->references('id')->on('ai_conversations')->cascadeOnDelete();
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('ai_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('guest_key', 64)->nullable();
            $table->string('locale', 5)->default('ar');
            $table->date('date');
            $table->json('request');
            $table->json('plan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_plans');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_requests');
    }
};
