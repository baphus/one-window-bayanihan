<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enforce one live (non-deleted) address row per client.
 *
 * Dedupes first: for each client_id holding more than one row with
 * is_deleted = false AND deleted_at IS NULL, the earliest row (ORDER BY
 * created_at, id) survives and the rest are soft-deleted in a single mass
 * UPDATE through the query builder — no model events. Duplicate rows are NOT
 * compared on street: it is encrypted (EncryptedString cast), so ciphertext
 * equality says nothing about plaintext equality.
 *
 * Then a partial unique index keeps it that way at the database level.
 */
return new class extends Migration
{
    private const INDEX = 'client_addresses_one_live_per_client';

    public function up(): void
    {
        // Keeper per client = earliest live row by (created_at, id); everything
        // else live is a duplicate. Subqueries read the pre-update snapshot, so
        // one statement is enough.
        DB::table('client_addresses')
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->whereNotIn('id', function ($query) {
                $query->selectRaw('DISTINCT ON (client_id) id')
                    ->from('client_addresses')
                    ->where('is_deleted', false)
                    ->whereNull('deleted_at')
                    ->orderBy('client_id')
                    ->orderBy('created_at')
                    ->orderBy('id');
            })
            ->update([
                'is_deleted' => true,
                'deleted_at' => DB::raw('now()'),
                'deleted_by' => null,
            ]);

        DB::statement(
            'CREATE UNIQUE INDEX '.self::INDEX
            .' ON client_addresses (client_id)'
            .' WHERE is_deleted = false AND deleted_at IS NULL'
        );
    }

    /**
     * Drops the index only. The dedupe is deliberately not restored: we cannot
     * tell which soft-deleted rows this migration removed versus rows deleted
     * through the app, so un-deleting them would resurrect garbage (lossy).
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};
