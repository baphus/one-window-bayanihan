<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_client_request_items', function (Blueprint $table): void {
            $table->uuid('source_requirement_id')->nullable()->after('request_id');
            $table->text('description')->nullable()->after('label');
            $table->string('canonical_key', 255)->nullable()->after('description');
            $table->foreign('source_requirement_id')->references('id')->on('referral_service_requirements')->nullOnDelete();
            $table->unique(['request_id', 'canonical_key']);
        });
    }

    public function down(): void
    {
        Schema::table('referral_client_request_items', function (Blueprint $table): void {
            $table->dropUnique(['request_id', 'canonical_key']);
            $table->dropForeign(['source_requirement_id']);
            $table->dropColumn(['source_requirement_id', 'description', 'canonical_key']);
        });
    }
};
