<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_alert_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('alert_type');
            $table->string('severity');
            $table->text('message');
            $table->jsonb('metadata')->nullable();
            $table->boolean('sent_email')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_alert_logs');
    }
};
