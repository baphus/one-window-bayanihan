<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retired chatbot vector corpus (see DB_SCHEMA_DEAD_TABLE_AUDIT_2026-09-16).
        // Original create migrations are stubbed no-ops; this removes the table
        // on databases that still carry it from the earlier implementation.
        Schema::dropIfExists('chatbot_embeddings');
    }

    public function down(): void
    {
        // Do not recreate: the vector infrastructure is retired and the
        // original create migrations are intentionally empty.
    }
};
