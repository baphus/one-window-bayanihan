<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('case_id')->nullable();
            $table->string('type'); // case_report_pdf, system_report_pdf, cases_export, clients_export, referrals_export, reports_export, admin_full_export
            $table->string('filename');
            $table->string('path')->nullable(); // S3 path, null while pending
            $table->bigInteger('file_size')->nullable(); // bytes
            $table->string('mime_type')->nullable();
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('case_id')->references('id')->on('cases')->nullOnDelete();

            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
    }
};
