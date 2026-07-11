<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only, client-facing case history. Rows are never updated or
        // deleted — corrections are recorded as new events. Content must be
        // publishable to the case's client as-is (no staff names, no internals).
        Schema::create('case_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Monotonic insertion order — deterministic tiebreaker for events
            // sharing the same occurred_at second.
            $table->bigInteger('sequence')->generatedAs()->unique();
            $table->uuid('case_id');
            $table->uuid('referral_id')->nullable();
            $table->string('type', 50);
            $table->string('title');
            $table->text('description')->nullable();
            $table->jsonb('meta')->nullable();
            $table->string('actor_type', 20)->default('system')->comment('agency | case_manager | system');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('case_id')->references('id')->on('cases')->onDelete('restrict');
            $table->foreign('referral_id')->references('id')->on('referrals')->onDelete('restrict');

            $table->index(['case_id', 'occurred_at']);
            $table->index('referral_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_events');
    }
};
