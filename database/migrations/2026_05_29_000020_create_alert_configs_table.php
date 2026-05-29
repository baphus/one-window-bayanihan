<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('alert_type');
            $table->boolean('enabled')->default(true);
            $table->decimal('threshold_value', 10, 2)->nullable();
            $table->jsonb('email_recipients')->default('[]');
            $table->boolean('notify_in_app')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_configs');
    }
};
