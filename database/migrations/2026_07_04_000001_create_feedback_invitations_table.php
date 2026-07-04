<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add active/default state columns to servqual_configs
        Schema::table('servqual_configs', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('questions');
            $table->timestamp('activated_at')->nullable()->after('is_active');
        });

        // Partial unique index: only one active config per agency
        DB::statement(
            'CREATE UNIQUE INDEX servqual_configs_active_agency_unique
             ON servqual_configs (agency_id) WHERE is_active = true'
        );

        // Create feedback_invitations table
        Schema::create('feedback_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('case_id');
            $table->uuid('agency_id');
            $table->uuid('referral_id');
            $table->string('client_email')->nullable();
            $table->string('token_prefix', 16);
            $table->string('token_hash', 64);
            $table->string('service_name')->nullable();
            $table->string('snapshot_source', 32)->default('agency_active_form');
            $table->json('form_snapshot');
            $table->json('rating_labels');
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('used_feedback_id')->nullable()->unique();
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->onDelete('restrict');
            $table->foreign('agency_id')->references('id')->on('agencies')->onDelete('restrict');
            $table->foreign('referral_id')->references('id')->on('referrals')->onDelete('set null');
            $table->foreign('used_feedback_id')->references('id')->on('feedback')->onDelete('set null');

            // Prevent duplicate invitations for the same case/agency/referral
            $table->unique(['case_id', 'agency_id', 'referral_id'], 'feedback_invitations_unique');
        });

        // Index for token prefix lookups
        DB::statement(
            'CREATE INDEX feedback_invitations_token_prefix_idx ON feedback_invitations (token_prefix)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_invitations');

        DB::statement('DROP INDEX IF EXISTS servqual_configs_active_agency_unique');

        Schema::table('servqual_configs', function (Blueprint $table) {
            $table->dropColumn('activated_at');
            $table->dropColumn('is_active');
        });
    }
};
