<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampTz('occurred_at');
            $table->index(['occurred_at', 'id']);
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_role')->nullable();
            $table->string('module', 50)->index();
            $table->string('action', 50)->index();
            $table->text('description')->nullable();
            $table->uuid('entity_id')->nullable()->index();
            $table->foreignUuid('audit_log_id')->nullable()->unique()->constrained('audit_logs')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
    }
};
