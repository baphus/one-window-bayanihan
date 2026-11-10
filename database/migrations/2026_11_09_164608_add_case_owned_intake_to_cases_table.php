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
            // Nullable during staged cutover: existing writers still create client-backed cases.
            $table->foreignUuid('ofw_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index('ofw_user_id');
            $table->enum('client_type', ['OFW', 'NEXT_OF_KIN'])->nullable();
            $table->date('intake_date')->nullable();
            foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'email', 'contact_number'] as $field) {
                $table->text($field)->nullable();
            }
            $table->enum('sex', ['MALE', 'FEMALE'])->nullable();
            $table->enum('civil_status', ['SINGLE', 'MARRIED', 'WIDOWED', 'SEPARATED', 'ANNULLED', 'OTHER'])->nullable();
            $table->text('civil_status_other')->nullable();
            $table->enum('deployment_mode', ['AGENCY_HIRED', 'DIRECT_HIRED', 'TOURIST_VISIT', 'BUSINESS', 'INVITATION'])->nullable();
            $table->enum('employment_status', ['ON_SITE', 'FOR_DEPLOYMENT', 'RETURNED'])->nullable();
            foreach (['region', 'province', 'city_municipality', 'barangay'] as $field) {
                $table->string('address_'.$field)->nullable();
                $table->string('address_'.$field.'_code', 20)->nullable();
            }
            $table->text('address_street')->nullable();
            foreach (['employer_name', 'position', 'country', 'last_country', 'last_position'] as $field) {
                $table->text('employment_'.$field)->nullable();
            }
            foreach (['start_date', 'end_date', 'date_of_arrival'] as $field) {
                $table->date('employment_'.$field)->nullable();
            }
            foreach (['nok_name', 'nok_relationship', 'nok_contact_number', 'nok_email', 'staff_summary', 'consent_evidence'] as $field) {
                $table->text($field)->nullable();
            }
            foreach (['proposed', 'confirmed'] as $kind) {
                $table->jsonb($kind.'_assessments')->nullable();
                $table->text($kind.'_assessment_other')->nullable();
                $table->foreignUuid($kind.'_assessment_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp($kind.'_assessment_at')->nullable();
            }
            $table->string('consent_text_version')->nullable();
            $table->string('consent_method')->nullable();
            $table->foreignUuid('consent_accepted_by')->nullable()->constrained('users')->nullOnDelete();
        });

        foreach (['proposed', 'confirmed'] as $kind) {
            DB::statement(<<<SQL
                ALTER TABLE cases ADD CONSTRAINT cases_{$kind}_assessments_check CHECK (
                    {$kind}_assessments IS NULL OR (
                        jsonb_typeof({$kind}_assessments) = 'array'
                        AND {$kind}_assessments <@ '["DISTRESSED_OFW","MEDICALLY_REPATRIATED","BURIAL_ASSISTANCE_CONCERN","POSSIBLE_LOCAL_EMPLOYMENT","PSYCHOSOCIAL_MEDICAL_INTERVENTION","SKILLS_ENHANCEMENT_TRAINING","LEGAL_ASSISTANCE","OTHER"]'::jsonb
                    )
                )
            SQL);
            DB::statement("ALTER TABLE cases ADD CONSTRAINT cases_{$kind}_assessment_other_check CHECK (NOT ({$kind}_assessments @> '[\"OTHER\"]'::jsonb) OR {$kind}_assessment_other IS NOT NULL)");
        }
        DB::statement("ALTER TABLE cases ADD CONSTRAINT cases_civil_status_other_check CHECK (civil_status <> 'OTHER' OR civil_status_other IS NOT NULL)");
    }

    public function down(): void
    {
        // Rollback discards newly recorded intake facts; use only on disposable local data.
        foreach (['proposed', 'confirmed'] as $kind) {
            DB::statement("ALTER TABLE cases DROP CONSTRAINT cases_{$kind}_assessments_check");
            DB::statement("ALTER TABLE cases DROP CONSTRAINT cases_{$kind}_assessment_other_check");
        }
        DB::statement('ALTER TABLE cases DROP CONSTRAINT cases_civil_status_other_check');
        Schema::table('cases', function (Blueprint $table) {
            foreach (['ofw_user_id', 'consent_accepted_by', 'proposed_assessment_by', 'confirmed_assessment_by'] as $field) {
                $table->dropForeign([$field]);
            }
            $table->dropColumn([
                'ofw_user_id', 'client_type', 'intake_date', 'first_name', 'middle_name', 'last_name', 'suffix',
                'date_of_birth', 'email', 'contact_number', 'sex', 'civil_status', 'civil_status_other',
                'deployment_mode', 'employment_status', 'address_region', 'address_province',
                'address_city_municipality', 'address_barangay', 'address_region_code', 'address_province_code',
                'address_city_municipality_code', 'address_barangay_code', 'address_street',
                'employment_employer_name', 'employment_position', 'employment_country', 'employment_last_country',
                'employment_last_position', 'employment_start_date', 'employment_end_date', 'employment_date_of_arrival',
                'nok_name', 'nok_relationship', 'nok_contact_number', 'nok_email', 'staff_summary',
                'proposed_assessments', 'proposed_assessment_other', 'proposed_assessment_by', 'proposed_assessment_at',
                'confirmed_assessments', 'confirmed_assessment_other', 'confirmed_assessment_by', 'confirmed_assessment_at',
                'consent_text_version', 'consent_method', 'consent_evidence', 'consent_accepted_by',
            ]);
        });
    }
};
