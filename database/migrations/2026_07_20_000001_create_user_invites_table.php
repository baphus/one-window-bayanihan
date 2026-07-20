<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('role');
            $table->uuid('agcy_id')->nullable();
            $table->string('token')->unique();
            $table->timestamp('expires_at');
            $table->uuid('created_by');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('agcy_id')->references('id')->on('agencies')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invites');
    }
};
