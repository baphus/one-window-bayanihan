<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (Throwable $e) {
            Log::warning('pgvector extension not available locally. Run migrations on a PostgreSQL instance with pgvector installed (Supabase includes it).', [
                'error' => $e->getMessage(),
            ]);
        }

    }

    public function down(): void {}
};
