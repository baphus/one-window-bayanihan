<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Make cases.user_id nullable to support self-filed intakes
     * that have no Case Manager assigned yet.
     *
     * Postgres RLS policies referencing user_id block the standard
     * ALTER COLUMN ... TYPE approach, so we use raw DDL that only
     * modifies nullability (not the type), which is not blocked.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE cases ALTER COLUMN user_id DROP NOT NULL');
    }

    public function down(): void
    {
        // Only safe if no nulls exist
        DB::statement('UPDATE cases SET user_id = (SELECT id FROM users WHERE role = \'CASE_MANAGER\' LIMIT 1) WHERE user_id IS NULL');
        DB::statement('ALTER TABLE cases ALTER COLUMN user_id SET NOT NULL');
    }
};
