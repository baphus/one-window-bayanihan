<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * CREATE INDEX CONCURRENTLY cannot run inside a transaction.
     * PostgreSQL requires CONCURRENTLY to run outside a transaction block.
     */
    public function __construct()
    {
        $this->withinTransaction = false;
    }

    /**
     * Run the migrations.
     *
     * Creates materialized views for analytics aggregations and partial
     * indexes for common analytics query patterns.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            echo 'ℹ️  Skipping analytics materialized views and indexes (current driver: '.DB::connection()->getDriverName().").\n";

            return;
        }

        // === MATERIALIZED VIEWS ===

        // 1. mv_daily_case_summary — daily case count aggregations
        //    Note: cases.status is a VARCHAR matching case_statuses.slug
        DB::statement('
            CREATE MATERIALIZED VIEW IF NOT EXISTS mv_daily_case_summary AS
            SELECT
                DATE(c.created_at) AS date,
                cs.slug AS status_slug,
                c.client_type,
                cc.name AS category_name,
                COUNT(*) AS case_count
            FROM cases c
            JOIN case_statuses cs ON cs.slug = c.status
            LEFT JOIN case_categories cc ON cc.id = c.category_id
            WHERE c.is_deleted = FALSE
            GROUP BY DATE(c.created_at), cs.slug, c.client_type, cc.name
            WITH DATA
        ');

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_daily_case_summary
                ON mv_daily_case_summary (date, status_slug, client_type, category_name)
        ');

        // 2. mv_agency_performance — daily agency-level referral metrics
        //    Note: referrals.status is a VARCHAR (not enum)
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS mv_agency_performance AS
            SELECT
                a.id AS agency_id,
                a.name AS agency_name,
                DATE(r.created_at) AS date,
                COUNT(*) AS total_referrals,
                COUNT(*) FILTER (WHERE r.status = 'COMPLETED') AS completed_referrals,
                COUNT(*) FILTER (WHERE r.status = 'REJECTED') AS rejected_referrals,
                AVG(EXTRACT(EPOCH FROM (r.updated_at - r.created_at)) / 86400)
                    FILTER (WHERE r.status = 'COMPLETED') AS avg_completion_days
            FROM referrals r
            JOIN agencies a ON a.id = r.agcy_id
            WHERE r.is_deleted = FALSE
            GROUP BY a.id, a.name, DATE(r.created_at)
            WITH DATA
        ");

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_agency_performance
                ON mv_agency_performance (agency_id, date)
        ');

        echo "✅ Created analytics materialized views.\n";

        // === CUSTOM ANALYTICS INDEXES ===

        try {
            // Index 1: feedback trend queries by agency over time
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_feedback_created_agency ON feedback (created_at, agency_id)');

            // Index 2: audit log transitions for bottleneck analysis
            // Note: audit_logs uses `timestamp` column, not `created_at`
            DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_audit_logs_transitions ON audit_logs (module, entity_id, timestamp) WHERE action IN ('status_change', 'updated')");

            // Index 3: aging case queries (non-deleted cases grouped by status)
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_cases_aging ON cases (created_at, status) WHERE is_deleted = FALSE');

            // Index 4: stalled referral detection (non-deleted referrals excluding terminal statuses)
            // Note: PostgreSQL does not allow subqueries in index predicates,
            // so terminal slugs are inlined
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
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Drop custom indexes on regular tables
        DB::statement('DROP INDEX IF EXISTS idx_feedback_created_agency');
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_transitions');
        DB::statement('DROP INDEX IF EXISTS idx_cases_aging');
        DB::statement('DROP INDEX IF EXISTS idx_referrals_stalled');

        // Drop materialized views (indexes on them cascade automatically)
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_agency_performance');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_daily_case_summary');

        echo "✅ Dropped analytics materialized views and indexes.\n";
    }
};
