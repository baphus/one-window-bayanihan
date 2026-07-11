<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Finalized archive bundles; a period is prunable only once its row exists here.
        Schema::create('audit_archives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('period', 7)->unique(); // 'YYYY-MM'
            $table->string('path');
            $table->string('checksum', 64); // SHA-256 of the bundle file
            $table->unsignedBigInteger('row_count');
            $table->timestamp('first_entry_at');
            $table->timestamp('last_entry_at');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        // Chain anchors written by audit:prune so audit:verify can validate the
        // oldest surviving row after its predecessor has been deleted.
        Schema::create('audit_chain_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('anchor_hash', 64); // expected prev_hash of the oldest surviving row
            $table->timestamp('pruned_through');
            $table->string('bundle_manifest_path')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_chain_checkpoints');
        Schema::dropIfExists('audit_archives');
    }
};
