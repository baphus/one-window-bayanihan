<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add a text column to store the embedded content for FTS.
        Schema::table('chatbot_embeddings', function ($table) {
            $table->text('content')->nullable()->after('heading');
        });

        // Add a tsvector generated column and GIN index for fast full-text search.
        DB::statement("
            ALTER TABLE chatbot_embeddings
            ADD COLUMN ts_content tsvector
            GENERATED ALWAYS AS (to_tsvector('english', coalesce(content, ''))) STORED
        ");

        DB::statement('
            CREATE INDEX chatbot_embeddings_ts_content_idx
            ON chatbot_embeddings USING GIN (ts_content)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS chatbot_embeddings_ts_content_idx');

        Schema::table('chatbot_embeddings', function ($table) {
            $table->dropColumn(['ts_content', 'content']);
        });
    }
};
