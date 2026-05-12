<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $fkTables = [
        'users', 'agencies', 'services', 'service_requirements',
        'cases', 'clients', 'case_documents', 'referrals',
        'referral_comments', 'milestones', 'referral_attachments', 'audit_logs',
    ];

    public function up(): void
    {
        // --- 1. ADD FK CONSTRAINTS ON deleted_by ---
        foreach ($this->fkTables as $table) {
            $fkName = "{$table}_deleted_by_foreign";
            Schema::table($table, function (Blueprint $t) use ($fkName) {
                $t->foreign('deleted_by', $fkName)->references('id')->on('users')->onDelete('restrict');
            });
        }

        // --- 2. RENAME COLUMNS ---

        // clients.contact -> contact_number
        Schema::table('clients', function (Blueprint $t) {
            $t->renameColumn('contact', 'contact_number');
        });

        // next_of_kin.address -> full_address
        Schema::table('next_of_kin', function (Blueprint $t) {
            $t->renameColumn('address', 'full_address');
        });

        // referral_attachments: drop FK on uploaded_by, rename, re-add as user_id
        Schema::table('referral_attachments', function (Blueprint $t) {
            $t->dropForeign(['uploaded_by']);
            $t->renameColumn('uploaded_by', 'user_id');
        });
        Schema::table('referral_attachments', function (Blueprint $t) {
            $t->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::table('referral_attachments', function (Blueprint $t) {
            $t->renameColumn('file_url', 'file_path');
            $t->renameColumn('mime_type', 'file_type');
        });

        // --- 3. CHANGE TYPES ---
        if (DB::getDriverName() !== 'sqlite') {
            // agencies.contact_info: text -> varchar(255)
            DB::statement("ALTER TABLE agencies ALTER COLUMN contact_info TYPE VARCHAR(255)");

            // referral_attachments.file_path: varchar -> text
            DB::statement("ALTER TABLE referral_attachments ALTER COLUMN file_path TYPE TEXT");
        }

        // --- 4. ADD CHECK CONSTRAINTS ---
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_sex_check");

            // Normalize existing data before adding strict constraint
            DB::table('clients')->whereNotNull('sex')->whereNotIn('sex', ['MALE', 'FEMALE'])->update([
                'sex' => DB::raw("UPPER(sex)"),
            ]);

            DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_sex_check CHECK (sex IS NULL OR sex IN ('MALE', 'FEMALE'))");

            DB::statement("ALTER TABLE referrals DROP CONSTRAINT IF EXISTS referrals_decision_check");

            DB::table('referrals')->whereNotNull('decision')->whereNotIn('decision', ['ACCEPT', 'REJECT'])->update([
                'decision' => DB::raw("UPPER(decision)"),
            ]);

            DB::statement("ALTER TABLE referrals ADD CONSTRAINT referrals_decision_check CHECK (decision IS NULL OR decision IN ('ACCEPT', 'REJECT'))");

            DB::statement("ALTER TABLE services DROP CONSTRAINT IF EXISTS services_processing_days_check");
            DB::statement("ALTER TABLE services ADD CONSTRAINT services_processing_days_check CHECK (processing_days IS NULL OR (processing_days >= 0 AND processing_days <= 365))");
        }

        // --- 5. DROP REDUNDANT COLUMN ---
        Schema::table('next_of_kin', function (Blueprint $t) {
            $t->dropColumn('contact');
        });
    }

    public function down(): void
    {
        // Reverse order

        // --- 5. RESTORE REDUNDANT COLUMN ---
        Schema::table('next_of_kin', function (Blueprint $t) {
            $t->string('contact')->nullable()->after('email');
        });

        // --- 4. DROP CHECK CONSTRAINTS ---
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_sex_check");
            DB::statement("ALTER TABLE referrals DROP CONSTRAINT IF EXISTS referrals_decision_check");
            DB::statement("ALTER TABLE services DROP CONSTRAINT IF EXISTS services_processing_days_check");
        }

        // --- 3. REVERT TYPES ---
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE agencies ALTER COLUMN contact_info TYPE TEXT");
            DB::statement("ALTER TABLE referral_attachments ALTER COLUMN file_path TYPE VARCHAR(255)");
        }

        // --- 2. REVERT COLUMN RENAMES ---
        Schema::table('referral_attachments', function (Blueprint $t) {
            $t->renameColumn('file_path', 'file_url');
            $t->renameColumn('file_type', 'mime_type');
        });

        Schema::table('referral_attachments', function (Blueprint $t) {
            $t->dropForeign(['user_id']);
            $t->renameColumn('user_id', 'uploaded_by');
        });
        Schema::table('referral_attachments', function (Blueprint $t) {
            $t->foreign('uploaded_by')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::table('next_of_kin', function (Blueprint $t) {
            $t->renameColumn('full_address', 'address');
        });

        Schema::table('clients', function (Blueprint $t) {
            $t->renameColumn('contact_number', 'contact');
        });

        // --- 1. DROP FK CONSTRAINTS ON deleted_by ---
        foreach ($this->fkTables as $table) {
            $fkName = "{$table}_deleted_by_foreign";
            Schema::table($table, function (Blueprint $t) use ($fkName) {
                $t->dropForeign($fkName);
            });
        }
    }
};
