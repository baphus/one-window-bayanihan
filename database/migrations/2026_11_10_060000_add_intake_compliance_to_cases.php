<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // Encrypted at rest by the model cast (same treatment as other intake PII).
            $table->text('employment_employer_address')->nullable();
            $table->text('last_correction_reason')->nullable();
            $table->string('return_reason', 30)->nullable();
            $table->text('missing_document_reasons')->nullable();
            $table->jsonb('requested_document_slots')->nullable();
            $table->text('document_request_reason')->nullable();
            $table->foreignUuid('document_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('document_requested_at')->nullable();
            $table->jsonb('reviewed_document_ids')->nullable();
            $table->foreignUuid('assigned_case_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index('assigned_case_manager_id');
        });

        // Null remains valid for legacy/client-backed rows created before this cutover.
        DB::statement("ALTER TABLE cases ADD CONSTRAINT cases_return_reason_check CHECK (return_reason IS NULL OR return_reason IN ('DISTRESSED_WORKER','TERMINATION','OTHERS'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cases DROP CONSTRAINT cases_return_reason_check');

        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['assigned_case_manager_id']);
            $table->dropConstrainedForeignId('assigned_case_manager_id');
            $table->dropConstrainedForeignId('document_requested_by');
            $table->dropColumn([
                'last_correction_reason', 'employment_employer_address', 'return_reason',
                'missing_document_reasons', 'requested_document_slots', 'document_request_reason',
                'document_requested_at', 'reviewed_document_ids',
            ]);
        });
    }
};
