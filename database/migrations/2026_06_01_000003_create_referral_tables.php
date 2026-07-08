<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===================================================================
        // REFERRALS (merged: create + decision_comment + type + SLA columns)
        // ===================================================================
        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('required_services');
            $table->text('notes')->nullable();
            $table->string('status', 50)->default('PENDING');
            $table->string('decision', 20)->nullable();
            $table->text('decision_comment')->nullable();
            $table->uuid('case_id');
            $table->uuid('agcy_id');
            $table->string('type', 20)->default('standard')->comment('standard | intervention');
            $table->timestamp('first_action_at')->nullable();
            $table->timestamp('referral_assigned_at')->nullable();
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('case_id')->references('id')->on('cases')->onDelete('restrict');
            $table->foreign('agcy_id')->references('id')->on('agencies')->onDelete('restrict');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE referrals ADD CONSTRAINT referrals_decision_check CHECK (decision IS NULL OR decision IN ('ACCEPT', 'REJECT'))");

        // ===================================================================
        // MILESTONES
        // ===================================================================
        Schema::create('milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->uuid('refr_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('refr_id')->references('id')->on('referrals')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        // ===================================================================
        // REFERRAL ATTACHMENTS (renamed columns + versioning)
        // ===================================================================
        Schema::create('referral_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referral_id');
            $table->string('file_name');
            $table->text('file_path');
            $table->string('file_type', 50)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('replaces_id')->nullable();
            $table->uuid('version_group_id')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('referral_id')->references('id')->on('referrals')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        // Self-referencing FK needs to be added after the table is created (PostgreSQL)
        Schema::table('referral_attachments', function (Blueprint $table) {
            $table->foreign('replaces_id')->references('id')->on('referral_attachments')->onDelete('set null');
        });

        // ===================================================================
        // REFERRAL COMMENTS
        // ===================================================================
        Schema::create('referral_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('refr_id');
            $table->uuid('parent_id')->nullable();
            $table->text('content');
            $table->string('visibility', 50);
            $table->boolean('is_edited')->default(false);
            $table->uuid('user_id');
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('refr_id')->references('id')->on('referrals')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::table('referral_comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('referral_comments')->onDelete('restrict');
        });

        // ===================================================================
        // CASE COMMENTS
        // ===================================================================
        Schema::create('case_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('case_id');
            $table->uuid('parent_id')->nullable();
            $table->text('content');
            $table->boolean('is_edited')->default(false);
            $table->uuid('user_id');
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('case_id')->references('id')->on('cases')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::table('case_comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('case_comments')->onDelete('restrict');
        });

        // ===================================================================
        // CASE DOCUMENTS
        // ===================================================================
        Schema::create('case_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('file_name');
            $table->text('file_path');
            $table->string('file_type', 50)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->uuid('case_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('case_id')->references('id')->on('cases')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        // ===================================================================
        // REFERRAL COMPLIANCE REQUIREMENTS
        // ===================================================================
        Schema::create('referral_compliance_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referral_id');
            $table->string('service_name', 255);
            $table->string('requirement_name', 255);
            $table->string('status', 20)->default('PENDING');
            $table->uuid('fulfilled_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('referral_id')->references('id')->on('referrals')->onDelete('restrict');
            $table->foreign('fulfilled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['referral_id', 'status']);
            $table->index('status');
        });

        // ===================================================================
        // CASE NOTIFICATIONS
        // ===================================================================
        Schema::create('case_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('case_id')->constrained('cases')->onDelete('cascade');
            $table->string('client_email');
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('related_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'client_email']);
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_compliance_requirements');
        Schema::dropIfExists('case_notifications');
        Schema::dropIfExists('case_comments');
        Schema::dropIfExists('case_documents');
        Schema::dropIfExists('referral_comments');
        Schema::dropIfExists('referral_attachments');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('referrals');
    }
};
