<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_id');
            $table->uuid('source_client_id')->nullable();
            $table->text('payload_encrypted')->nullable();
            $table->smallInteger('payload_schema_version');
            $table->bigInteger('revision')->default(1);
            $table->string('state', 20)->default('EDITING');
            $table->uuid('published_case_id')->nullable()->unique();
            // Historical metadata only; this intentionally has no foreign key.
            $table->uuid('legacy_case_id')->nullable()->unique();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('source_client_id')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('published_case_id')->references('id')->on('cases')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE case_drafts ADD CONSTRAINT case_drafts_revision_check CHECK (revision >= 1)');
        DB::statement("ALTER TABLE case_drafts ADD CONSTRAINT case_drafts_state_check CHECK (state IN ('EDITING', 'PUBLISHED', 'DISCARDED'))");
        DB::statement("ALTER TABLE case_drafts ADD CONSTRAINT case_drafts_published_check CHECK (state <> 'PUBLISHED' OR (published_case_id IS NOT NULL AND published_at IS NOT NULL))");
        DB::statement("ALTER TABLE case_drafts ADD CONSTRAINT case_drafts_editing_case_check CHECK (state <> 'EDITING' OR published_case_id IS NULL)");
        DB::statement("ALTER TABLE case_drafts ADD CONSTRAINT case_drafts_terminal_payload_check CHECK (state = 'EDITING' OR payload_encrypted IS NULL)");
        DB::statement('CREATE INDEX case_drafts_owner_state_updated_at_index ON case_drafts (owner_id, state, updated_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('case_drafts');
    }
};
