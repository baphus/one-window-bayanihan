<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Insertion-ordered chain key. Timestamps are second-precision and ids
        // are random UUIDs, so (timestamp, id) cannot order same-second rows —
        // the sequence, assigned under the chain advisory lock, can.
        // Existing rows receive values in physical order, which matches
        // insertion order closely enough for the pre-fix era (verification of
        // that era is governed by audit.chain_verified_from anyway).
        DB::statement('ALTER TABLE audit_logs ADD COLUMN chain_seq BIGSERIAL');
        DB::statement('CREATE UNIQUE INDEX idx_audit_logs_chain_seq ON audit_logs (chain_seq)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_chain_seq');
        DB::statement('ALTER TABLE audit_logs DROP COLUMN IF EXISTS chain_seq');
    }
};
