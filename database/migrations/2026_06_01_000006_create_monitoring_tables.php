<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- audit_logs ---
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('action', 50);
            $table->string('module');
            $table->uuid('entity_id')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('old_value')->nullable();
            $table->jsonb('new_value')->nullable();
            $table->uuid('user_id')->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('user_id', 'audit_logs_user_id_foreign')
                ->references('id')->on('users')->onDelete('restrict');
            $table->foreign('deleted_by', 'audit_logs_deleted_by_foreign')
                ->references('id')->on('users')->onDelete('restrict');

            $table->index('entity_id', 'idx_audit_logs_entity_id');
        });

        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action::text IN ('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'ARCHIVE', 'UNARCHIVE', 'PUBLISH'))");

        // --- email_logs ---
        Schema::create('email_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('to_email');
            $table->string('subject');
            $table->string('mailable_type');
            $table->string('status');
            $table->uuid('job_uuid')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');

        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');

        Schema::dropIfExists('audit_logs');
    }
};
