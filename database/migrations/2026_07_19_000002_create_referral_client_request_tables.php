<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_client_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referral_id');
            $table->uuid('creator_user_id');
            $table->string('type', 32);
            $table->string('title');
            $table->text('instructions');
            $table->string('status', 32)->default('OPEN');
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('referral_id')->references('id')->on('referrals')->restrictOnDelete();
            $table->foreign('creator_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['referral_id', 'status']);
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->uuid('client_request_id')->nullable()->unique()->after('refr_id');
            $table->foreign('client_request_id')->references('id')->on('referral_client_requests')->nullOnDelete();
        });

        Schema::create('referral_client_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('request_id')->references('id')->on('referral_client_requests')->cascadeOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['request_id', 'sort_order']);
        });

        Schema::create('referral_client_access_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->string('token_hash', 255)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->uuid('issued_by');
            $table->text('recipient_snapshot');
            $table->timestamp('first_used_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('referral_client_requests')->restrictOnDelete();
            $table->foreign('revoked_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('issued_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['request_id', 'expires_at']);
        });

        Schema::create('referral_client_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->text('body');
            $table->string('sender_kind', 24);
            $table->uuid('user_id')->nullable();
            $table->uuid('access_link_id')->nullable();
            $table->string('kind', 24)->default('MESSAGE');
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('request_id')->references('id')->on('referral_client_requests')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('access_link_id')->references('id')->on('referral_client_access_links')->restrictOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['request_id', 'created_at']);
        });

        DB::statement("ALTER TABLE referral_client_requests ADD CONSTRAINT referral_client_requests_type_check CHECK (type IN ('DOCUMENT_REQUEST', 'QUESTION', 'INFORMATION_UPDATE'))");
        DB::statement("ALTER TABLE referral_client_requests ADD CONSTRAINT referral_client_requests_status_check CHECK (status IN ('OPEN', 'IN_PROGRESS', 'CLIENT_RESPONDED', 'COMPLETED', 'CANCELLED'))");
        DB::statement("ALTER TABLE referral_client_messages ADD CONSTRAINT referral_client_messages_sender_kind_check CHECK (sender_kind IN ('AGENCY_USER', 'CLIENT_ACCESS'))");
        DB::statement("ALTER TABLE referral_client_messages ADD CONSTRAINT referral_client_messages_kind_check CHECK (kind IN ('MESSAGE', 'SYSTEM', 'REVISION'))");
        DB::statement("ALTER TABLE referral_client_messages ADD CONSTRAINT referral_client_messages_sender_check CHECK ((sender_kind = 'AGENCY_USER' AND user_id IS NOT NULL AND access_link_id IS NULL) OR (sender_kind = 'CLIENT_ACCESS' AND user_id IS NULL AND access_link_id IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropForeign(['client_request_id']);
            $table->dropUnique(['client_request_id']);
            $table->dropColumn('client_request_id');
        });

        Schema::dropIfExists('referral_client_messages');
        Schema::dropIfExists('referral_client_access_links');
        Schema::dropIfExists('referral_client_request_items');
        Schema::dropIfExists('referral_client_requests');
    }
};
