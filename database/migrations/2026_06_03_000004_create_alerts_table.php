<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 50);
            $table->string('severity', 20)->default('info');
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->string('entity_type', 50)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->uuid('assigned_to_id');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');

            $table->foreign('assigned_to_id')->references('id')->on('users');

            $table->index(['assigned_to_id', 'dismissed_at', 'read_at'], 'idx_alerts_assigned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
