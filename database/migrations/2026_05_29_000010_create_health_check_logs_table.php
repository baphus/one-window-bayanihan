<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_check_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('check_type');
            $table->string('status');
            $table->text('metric_value')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('checked_at')->useCurrent();
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_check_logs');
    }
};
