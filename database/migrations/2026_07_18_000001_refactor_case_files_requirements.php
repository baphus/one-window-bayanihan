<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('referral_compliance_requirements');

        Schema::table('referrals', function (Blueprint $table) {
            $table->json('requirements')->nullable()->after('required_services');
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->json('requirements')->nullable()->after('description');
        });

        Schema::table('case_documents', function (Blueprint $table) {
            $table->string('category', 255)->nullable()->after('file_type');
        });
    }

    public function down(): void
    {
        Schema::create('referral_compliance_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->string('service_name');
            $table->string('requirement_name');
            $table->string('status')->default('PENDING');
            $table->foreignUuid('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('remark')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->foreignUuid('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('requirements');
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->dropColumn('requirements');
        });

        Schema::table('case_documents', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
