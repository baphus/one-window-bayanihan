<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement('ALTER TABLE helpdesk_article_chunks ADD COLUMN IF NOT EXISTS embedding vector(1536)');
        } catch (Throwable $e) {
            echo "⚠️  vector column skipped — pgvector extension not available locally.\n";
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE helpdesk_article_chunks DROP COLUMN IF EXISTS embedding');
        } catch (Throwable $e) {
            // Column may not exist, ignore
        }
    }
};
