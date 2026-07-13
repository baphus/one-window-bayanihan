<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('survey_forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agency_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->foreign('agency_id')
                ->references('id')
                ->on('agencies')
                ->cascadeOnDelete();
        });

        // Partial unique index: only one active form per agency
        DB::statement('CREATE UNIQUE INDEX survey_forms_agency_active_unique ON survey_forms (agency_id) WHERE is_active = true');

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('survey_form_id');
            $table->string('type', 20); // 'likert', 'text', 'radio', 'checkbox', 'rating'
            $table->text('label');
            $table->jsonb('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('survey_form_id')
                ->references('id')
                ->on('survey_forms')
                ->cascadeOnDelete();

            $table->index('survey_form_id');
        });

        Schema::create('survey_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('survey_form_id')->nullable();
            $table->uuid('case_id');
            $table->uuid('agency_id');
            $table->uuid('referral_id');
            $table->string('client_name', 255);
            $table->string('client_email', 255);
            $table->string('service_name', 255);
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('survey_form_id')
                ->references('id')
                ->on('survey_forms')
                ->nullOnDelete();

            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->cascadeOnDelete();

            $table->foreign('agency_id')
                ->references('id')
                ->on('agencies')
                ->cascadeOnDelete();

            $table->foreign('referral_id')
                ->references('id')
                ->on('referrals')
                ->cascadeOnDelete();

            $table->unique(['case_id', 'agency_id', 'referral_id'], 'survey_invitations_case_agency_referral_unique');
            $table->index('token');
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('survey_invitation_id');
            $table->uuid('survey_question_id')->nullable();
            $table->text('answer')->nullable();
            $table->jsonb('selected_options')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('survey_invitation_id')
                ->references('id')
                ->on('survey_invitations')
                ->cascadeOnDelete();

            $table->foreign('survey_question_id')
                ->references('id')
                ->on('survey_questions')
                ->nullOnDelete();

            $table->index('survey_invitation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_invitations');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('survey_forms');
    }
};
