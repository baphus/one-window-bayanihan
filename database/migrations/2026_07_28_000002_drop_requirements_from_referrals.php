<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE referrals DROP COLUMN IF EXISTS requirements');
    }

    public function down(): void
    {
        // Re-add the column only if it doesn't exist (idempotent reverse)
        if (! Schema::hasColumn('referrals', 'requirements')) {
            Schema::table('referrals', function ($table) {
                $table->json('requirements')->nullable()->after('required_services');
            });
        }
    }
};
