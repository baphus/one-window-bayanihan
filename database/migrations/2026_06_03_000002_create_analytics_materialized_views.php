<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // 1. mv_daily_case_summary — daily case count aggregations
        //    Note: cases.status is a VARCHAR matching case_statuses.slug
        DB::statement('
            CREATE MATERIALIZED VIEW mv_daily_case_summary AS
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
            CREATE UNIQUE INDEX idx_mv_daily_case_summary
                ON mv_daily_case_summary (date, status_slug, client_type, category_name)
        ');

        // 2. mv_agency_performance — daily agency-level referral metrics
        //    Note: referrals.status is a VARCHAR matching case_statuses.slug
        DB::statement('
            CREATE MATERIALIZED VIEW mv_agency_performance AS
            SELECT
                a.id AS agency_id,
                a.name AS agency_name,
                DATE(r.created_at) AS date,
                COUNT(*) AS total_referrals,
                COUNT(*) FILTER (WHERE r.status = \'COMPLETED\') AS completed_referrals,
                COUNT(*) FILTER (WHERE r.status = \'REJECTED\') AS rejected_referrals,
                AVG(EXTRACT(EPOCH FROM (r.updated_at - r.created_at)) / 86400)
                    FILTER (WHERE r.status = \'COMPLETED\') AS avg_completion_days
            FROM referrals r
            JOIN agencies a ON a.id = r.agcy_id
            WHERE r.is_deleted = FALSE
            GROUP BY a.id, a.name, DATE(r.created_at)
            WITH DATA
        ');

        DB::statement('
            CREATE UNIQUE INDEX idx_mv_agency_performance
                ON mv_agency_performance (agency_id, date)
        ');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_agency_performance');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_daily_case_summary');
    }
};
