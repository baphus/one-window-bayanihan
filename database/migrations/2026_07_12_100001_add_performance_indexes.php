<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Allow running outside a transaction so CONCURRENTLY can be used later if needed.
     */
    public $withinTransaction = false;

    /**
     * Add performance indexes across all heavily-queried tables.
     *
     * Context:
     * - All tables use UUID primary keys
     * - Soft deletes use SoftDeleteFlag (is_deleted + deleted_at)
     * - Laravel's SoftDeletes adds WHERE deleted_at IS NULL automatically
     * - pg_trgm extension already enabled
     */
    public function up(): void
    {
        // =====================================================================
        // SECTION 1: B-tree indexes on FK and filter columns
        // These columns are queried constantly but have no indexes.
        // =====================================================================

        // cases table
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cases_user_id ON cases (user_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cases_status ON cases (status)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cases_client_id ON cases (client_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cases_category_id ON cases (category_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cases_case_issue_id ON cases (case_issue_id)');

        // referrals table
        DB::statement('CREATE INDEX IF NOT EXISTS idx_referrals_case_id ON referrals (case_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_referrals_agcy_id ON referrals (agcy_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_referrals_status ON referrals (status)');

        // milestones table
        DB::statement('CREATE INDEX IF NOT EXISTS idx_milestones_refr_id ON milestones (refr_id)');

        // referral_comments table
        DB::statement('CREATE INDEX IF NOT EXISTS idx_referral_comments_refr_id ON referral_comments (refr_id)');

        // referral_attachments table
        DB::statement('CREATE INDEX IF NOT EXISTS idx_referral_attachments_referral_id ON referral_attachments (referral_id)');

        // client-related tables
        DB::statement('CREATE INDEX IF NOT EXISTS idx_client_addresses_client_id ON client_addresses (client_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_client_employments_client_id ON client_employments (client_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_next_of_kin_client_id ON next_of_kin (client_id)');

        // case_documents table
        DB::statement('CREATE INDEX IF NOT EXISTS idx_case_documents_case_id ON case_documents (case_id)');

        // feedback tables (IF NOT EXISTS handles case where unique constraint already covers this)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_feedback_agency_id ON feedback (agency_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_feedback_servqual_responses_feedback_id ON feedback_servqual_responses (feedback_id)');

        // users table
        DB::statement('CREATE INDEX IF NOT EXISTS idx_users_agcy_id ON users (agcy_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_users_role ON users (role)');

        // services table
        DB::statement('CREATE INDEX IF NOT EXISTS idx_services_agcy_id ON services (agcy_id)');

        // email_logs table
        DB::statement('CREATE INDEX IF NOT EXISTS idx_email_logs_status ON email_logs (status)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_email_logs_to_email ON email_logs (to_email)');

        // =====================================================================
        // SECTION 2: Composite indexes for common query patterns
        // These match the most frequent WHERE + ORDER BY combinations.
        // =====================================================================

        // Case Manager listing: WHERE status = ? AND user_id = ? ORDER BY created_at DESC
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cases_status_user_created ON cases (status, user_id, created_at DESC)');

        // Client.caseFile() relationship: WHERE client_id = ? AND deleted_at IS NULL ORDER BY created_at DESC, id DESC
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cases_client_created_id_active ON cases (client_id, created_at DESC, id DESC) WHERE deleted_at IS NULL');

        // Agency dashboard: WHERE agcy_id = ? AND status = ?
        DB::statement('CREATE INDEX IF NOT EXISTS idx_referrals_agcy_status ON referrals (agcy_id, status)');

        // Case detail referral section: WHERE case_id = ? AND status = ?
        DB::statement('CREATE INDEX IF NOT EXISTS idx_referrals_case_status ON referrals (case_id, status)');

        // Overdue referral queries: WHERE status IN (...) ORDER BY created_at
        DB::statement("CREATE INDEX IF NOT EXISTS idx_referrals_status_created_active ON referrals (status, created_at) WHERE status IN ('PENDING', 'PROCESSING', 'FOR_COMPLIANCE')");

        // Latest milestone lookups: WHERE refr_id = ? ORDER BY created_at DESC
        DB::statement('CREATE INDEX IF NOT EXISTS idx_milestones_refr_created ON milestones (refr_id, created_at DESC)');

        // Address lookups: WHERE client_id = ? AND is_deleted = false
        DB::statement('CREATE INDEX IF NOT EXISTS idx_client_addresses_client_deleted ON client_addresses (client_id, is_deleted)');

        // Unread notification count: WHERE notifiable_id = ? AND read_at IS NULL
        DB::statement('CREATE INDEX IF NOT EXISTS idx_notifications_notifiable_read ON notifications (notifiable_id, read_at)');

        // OFW notification listing: WHERE case_id = ? AND client_email = ? ORDER BY created_at DESC
        DB::statement('CREATE INDEX IF NOT EXISTS idx_case_notifications_case_email_created ON case_notifications (case_id, client_email, created_at DESC)');

        // =====================================================================
        // SECTION 3: pg_trgm GIN indexes for LIKE/ILIKE search
        // Extension pg_trgm is already enabled.
        // =====================================================================

        DB::statement('CREATE INDEX IF NOT EXISTS idx_clients_first_name_trgm ON clients USING gin (first_name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_clients_last_name_trgm ON clients USING gin (last_name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cases_case_number_trgm ON cases USING gin (case_number gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cases_tracker_number_trgm ON cases USING gin (tracker_number gin_trgm_ops)');

        // =====================================================================
        // SECTION 4: Partial indexes for soft-deleted records
        // Laravel's SoftDeletes adds WHERE deleted_at IS NULL to all queries,
        // so partial indexes targeting active records are highly selective.
        // =====================================================================

        DB::statement('CREATE INDEX IF NOT EXISTS idx_cases_status_active ON cases (status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_referrals_status_active ON referrals (status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_referrals_agcy_id_active ON referrals (agcy_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_clients_id_active ON clients (id) WHERE deleted_at IS NULL');
    }

    /**
     * Drop all performance indexes created by this migration.
     */
    public function down(): void
    {
        // Section 1: B-tree indexes on FK and filter columns
        DB::statement('DROP INDEX IF EXISTS idx_cases_user_id');
        DB::statement('DROP INDEX IF EXISTS idx_cases_status');
        DB::statement('DROP INDEX IF EXISTS idx_cases_client_id');
        DB::statement('DROP INDEX IF EXISTS idx_cases_category_id');
        DB::statement('DROP INDEX IF EXISTS idx_cases_case_issue_id');
        DB::statement('DROP INDEX IF EXISTS idx_referrals_case_id');
        DB::statement('DROP INDEX IF EXISTS idx_referrals_agcy_id');
        DB::statement('DROP INDEX IF EXISTS idx_referrals_status');
        DB::statement('DROP INDEX IF EXISTS idx_milestones_refr_id');
        DB::statement('DROP INDEX IF EXISTS idx_referral_comments_refr_id');
        DB::statement('DROP INDEX IF EXISTS idx_referral_attachments_referral_id');
        DB::statement('DROP INDEX IF EXISTS idx_client_addresses_client_id');
        DB::statement('DROP INDEX IF EXISTS idx_client_employments_client_id');
        DB::statement('DROP INDEX IF EXISTS idx_next_of_kin_client_id');
        DB::statement('DROP INDEX IF EXISTS idx_case_documents_case_id');
        DB::statement('DROP INDEX IF EXISTS idx_feedback_agency_id');
        DB::statement('DROP INDEX IF EXISTS idx_feedback_servqual_responses_feedback_id');
        DB::statement('DROP INDEX IF EXISTS idx_users_agcy_id');
        DB::statement('DROP INDEX IF EXISTS idx_users_role');
        DB::statement('DROP INDEX IF EXISTS idx_services_agcy_id');
        DB::statement('DROP INDEX IF EXISTS idx_email_logs_status');
        DB::statement('DROP INDEX IF EXISTS idx_email_logs_to_email');

        // Section 2: Composite indexes
        DB::statement('DROP INDEX IF EXISTS idx_cases_status_user_created');
        DB::statement('DROP INDEX IF EXISTS idx_cases_client_created_id_active');
        DB::statement('DROP INDEX IF EXISTS idx_referrals_agcy_status');
        DB::statement('DROP INDEX IF EXISTS idx_referrals_case_status');
        DB::statement('DROP INDEX IF EXISTS idx_referrals_status_created_active');
        DB::statement('DROP INDEX IF EXISTS idx_milestones_refr_created');
        DB::statement('DROP INDEX IF EXISTS idx_client_addresses_client_deleted');
        DB::statement('DROP INDEX IF EXISTS idx_notifications_notifiable_read');
        DB::statement('DROP INDEX IF EXISTS idx_case_notifications_case_email_created');

        // Section 3: pg_trgm GIN indexes
        DB::statement('DROP INDEX IF EXISTS idx_clients_first_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_clients_last_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_cases_case_number_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_cases_tracker_number_trgm');

        // Section 4: Partial indexes
        DB::statement('DROP INDEX IF EXISTS idx_cases_status_active');
        DB::statement('DROP INDEX IF EXISTS idx_referrals_status_active');
        DB::statement('DROP INDEX IF EXISTS idx_referrals_agcy_id_active');
        DB::statement('DROP INDEX IF EXISTS idx_clients_id_active');
    }
};
