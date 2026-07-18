<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_notifications', function (Blueprint $table): void {
            $table->string('event_key')->nullable();
            $table->string('delivery_status')->nullable();
            $table->timestamp('delivery_attempted_at')->nullable();
            $table->uuid('claim_token')->nullable();
            $table->unsignedInteger('claim_generation')->default(0);
            $table->unique('event_key');
            $table->unique('claim_token');
            $table->index(['delivery_status', 'delivery_attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('case_notifications', function (Blueprint $table): void {
            $table->dropIndex(['delivery_status', 'delivery_attempted_at']);
            $table->dropUnique(['event_key']);
            $table->dropColumn([
                'event_key',
                'delivery_status',
                'delivery_attempted_at',
                'claim_token',
                'claim_generation',
            ]);
        });
    }
};
