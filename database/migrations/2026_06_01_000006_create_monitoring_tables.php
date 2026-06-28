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

        // --- health_check_logs ---
        Schema::create('health_check_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('check_type');
            $table->string('status');
            $table->text('metric_value')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('checked_at')->useCurrent();
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('deleted_by', 'health_check_logs_deleted_by_foreign')
                ->references('id')->on('users')->onDelete('restrict');
        });

        // --- alert_configs ---
        Schema::create('alert_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('alert_type');
            $table->boolean('enabled')->default(true);
            $table->decimal('threshold_value', 10, 2)->nullable();
            $table->jsonb('email_recipients')->default('[]');
            $table->boolean('notify_in_app')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('deleted_by', 'alert_configs_deleted_by_foreign')
                ->references('id')->on('users')->onDelete('restrict');
        });

        // --- system_alert_logs ---
        Schema::create('system_alert_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('alert_type');
            $table->string('severity');
            $table->text('message');
            $table->jsonb('metadata')->nullable();
            $table->boolean('sent_email')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('deleted_by', 'system_alert_logs_deleted_by_foreign')
                ->references('id')->on('users')->onDelete('restrict');
        });

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

        // --- alerts ---
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 50);
            $table->string('severity', 20)->default('info');
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->string('entity_type', 50)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->uuid('assigned_to_id');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');

            $table->foreign('assigned_to_id', 'alerts_assigned_to_id_foreign')
                ->references('id')->on('users');

            $table->index(['assigned_to_id', 'dismissed_at', 'read_at'], 'idx_alerts_assigned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('system_alert_logs');
        Schema::dropIfExists('alert_configs');
        Schema::dropIfExists('health_check_logs');

        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');

        Schema::dropIfExists('audit_logs');
    }
};
