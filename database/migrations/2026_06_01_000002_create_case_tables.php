<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // === 1. clients (NO case_id — FK moved to cases.client_id) ===
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_initial', 1)->nullable();
            $table->string('suffix')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('sex', 10)->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('avatar_url')->nullable();
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_sex_check CHECK (sex IS NULL OR sex IN ('MALE', 'FEMALE'))");

        // === 2. cases ===
        Schema::create('cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('case_number')->unique();
            $table->string('client_type', 20);
            $table->string('vulnerability_indicator')->nullable();
            $table->string('nok_vulnerability_indicator')->nullable();
            $table->string('tracker_number')->unique();
            $table->text('summary')->nullable();
            $table->string('status', 50)->default('OPEN');
            $table->timestamp('closed_at', 0)->nullable();
            $table->timestamp('consent_given_at')->nullable();
            $table->uuid('user_id');
            $table->uuid('client_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->uuid('case_issue_id')->nullable();
            $table->jsonb('draft_client_data')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->string('escalation_reason')->nullable();
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('category_id')->references('id')->on('case_categories')->onDelete('restrict');
            $table->foreign('case_issue_id')->references('id')->on('case_issues')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        // === 3. client_addresses ===
        Schema::create('client_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->string('region')->nullable();
            $table->string('province')->nullable();
            $table->string('city_municipality')->nullable();
            $table->string('barangay')->nullable();
            $table->text('street')->nullable();
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        // === 4. client_employments ===
        Schema::create('client_employments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->string('employer_name')->nullable();
            $table->string('position')->nullable();
            $table->string('last_position')->nullable();
            $table->string('country')->nullable();
            $table->string('last_country')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('date_of_arrival')->nullable();
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });

        // === 5. next_of_kin (FK switched from cases to clients) ===
        Schema::create('next_of_kin', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('middle_initial', 1)->nullable();
            $table->string('relationship')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('phone_number', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('full_address')->nullable();
            $table->string('region')->nullable();
            $table->string('province')->nullable();
            $table->string('city_municipality')->nullable();
            $table->string('barangay')->nullable();
            $table->text('street')->nullable();
            $table->integer('sort_order')->nullable()->default(0);
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_of_kin');
        Schema::dropIfExists('client_employments');
        Schema::dropIfExists('client_addresses');
        Schema::dropIfExists('cases');
        Schema::dropIfExists('clients');
    }
};
