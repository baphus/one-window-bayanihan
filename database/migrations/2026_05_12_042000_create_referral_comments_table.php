<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('refr_id');
            $table->uuid('parent_id')->nullable();
            $table->text('content');
            $table->enum('visibility', ['INTERNAL', 'AGY_ONLY', 'CLIENT_VISIBLE']);
            $table->boolean('is_edited')->default(false);
            $table->uuid('user_id');
            $table->timestamps();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('refr_id')->references('id')->on('referrals')->onDelete('restrict');
            $table->foreign('parent_id')->references('id')->on('referral_comments')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_comments');
    }
};
