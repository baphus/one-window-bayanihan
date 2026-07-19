<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP POLICY IF EXISTS case_documents_agency_referred ON case_documents');
        DB::statement("CREATE POLICY case_documents_agency_referred ON case_documents FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND referral_id IS NOT NULL AND EXISTS (SELECT 1 FROM referrals r WHERE r.id = case_documents.referral_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)))");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS case_documents_agency_referred ON case_documents');
        DB::statement("CREATE POLICY case_documents_agency_referred ON case_documents FOR ALL TO PUBLIC USING (current_setting('app.user_role', TRUE) = 'AGENCY' AND EXISTS (SELECT 1 FROM cases c JOIN referrals r ON r.case_id = c.id WHERE c.id = case_id AND r.agcy_id = (SELECT u.agcy_id FROM users u WHERE u.id = current_setting('app.current_user_id', TRUE)::uuid)))");
    }
};
