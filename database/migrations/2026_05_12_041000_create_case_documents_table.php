<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('file_name');
            $table->text('file_path');
            $table->string('file_type', 50)->nullable();
            $table->uuid('case_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('case_id')->references('id')->on('cases')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_documents');
    }
};
