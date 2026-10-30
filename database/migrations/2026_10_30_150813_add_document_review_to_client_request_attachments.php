<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_client_message_attachments', function (Blueprint $table): void {
            $table->uuid('request_item_id')->nullable()->after('message_id');
            $table->string('review_status', 32)->default('ACCEPTED')->after('size');
            $table->string('visibility', 32)->default('ALL')->after('review_status');
            $table->string('sha256', 64)->nullable()->after('visibility');
            $table->foreign('request_item_id')->references('id')->on('referral_client_request_items')->nullOnDelete();
            $table->index(['request_item_id', 'review_status']);
        });
    }

    public function down(): void
    {
        Schema::table('referral_client_message_attachments', function (Blueprint $table): void {
            $table->dropForeign(['request_item_id']);
            $table->dropIndex(['request_item_id', 'review_status']);
            $table->dropColumn(['request_item_id', 'review_status', 'visibility', 'sha256']);
        });
    }
};
