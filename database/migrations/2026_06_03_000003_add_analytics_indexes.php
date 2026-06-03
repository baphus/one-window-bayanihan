<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * CREATE INDEX CONCURRENTLY cannot run inside a transaction.
     * PostgreSQL requires CONCURRENTLY to run outside a transaction block.
     * Setting via constructor since PHP 8.4 doesn't allow redeclaring
     * parent typed properties in anonymous child classes.
     */
    public function __construct()
    {
        $this->withinTransaction = false;
    }

    /**
     * Run the migrations.
     *
     * Adds partial indexes to support analytics queries:
     * - Feedback trend analysis by agency over time
     * - Audit log transition/bottleneck analysis
     * - Case aging reporting (non-deleted)
     * - Stalled referral detection (excludes terminal statuses)
     */
    public function up(): void
    {
        try {
            // Index 1: feedback trend queries by agency over time
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_feedback_created_agency ON feedback (created_at, agency_id)');

            // Index 2: audit log transitions for bottleneck analysis
            // Note: audit_logs uses `timestamp` column, not `created_at`
            DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_audit_logs_transitions ON audit_logs (module, entity_id, timestamp) WHERE action IN ('status_change', 'updated')");

            // Index 3: aging case queries (non-deleted cases grouped by status)
            // Note: cases uses varchar `status` column, not a FK `status_id`
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_cases_aging ON cases (created_at, status) WHERE is_deleted = FALSE');

            // Index 4: stalled referral detection (non-deleted referrals excluding terminal statuses)
            // Note: referrals uses varchar `status` column; PostgreSQL does not allow
            // subqueries in index predicates, so terminal slugs are inlined
            DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_referrals_stalled ON referrals (updated_at, status) WHERE is_deleted = FALSE AND status NOT IN ('COMPLETED', 'REJECTED')");

            echo "✅ Created analytics performance indexes.\n";
        } catch (Throwable $e) {
            Log::warning('Failed to create analytics indexes.', [
                'error' => $e->getMessage(),
            ]);
            echo '⚠️  Failed to create analytics indexes: '.$e->getMessage()."\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP INDEX IF EXISTS idx_feedback_created_agency');
            DB::statement('DROP INDEX IF EXISTS idx_audit_logs_transitions');
            DB::statement('DROP INDEX IF EXISTS idx_cases_aging');
            DB::statement('DROP INDEX IF EXISTS idx_referrals_stalled');

            echo "✅ Dropped analytics performance indexes.\n";
        } catch (Throwable $e) {
            Log::warning('Failed to drop analytics indexes.', [
                'error' => $e->getMessage(),
            ]);
            echo '⚠️  Failed to drop analytics indexes: '.$e->getMessage()."\n";
        }
    }
};
