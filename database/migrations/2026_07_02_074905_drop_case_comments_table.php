<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('case_comments');
    }

    public function down(): void
    {
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
    }
};
