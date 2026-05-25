<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('referral_attachments', function (Blueprint $table) {
            $table->uuid('replaces_id')->nullable()->after('uploaded_by');
            $table->uuid('version_group_id')->nullable()->after('replaces_id');
            $table->boolean('is_archived')->default(false)->after('version_group_id');

            $table->foreign('replaces_id')
                ->references('id')
                ->on('referral_attachments')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('referral_attachments', function (Blueprint $table) {
            $table->dropForeign(['replaces_id']);
            $table->dropColumn(['replaces_id', 'version_group_id', 'is_archived']);
        });
    }
};
