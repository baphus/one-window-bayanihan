<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('type', 20)->nullable()->after('id');
            $table->uuid('user_id')->nullable()->after('type');
            $table->uuid('ofw_client_id')->nullable()->after('user_id');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('ofw_client_id')->references('id')->on('clients')->nullOnDelete();

            // Nullable unique: inline OFWs created by NEXT_OF_KIN keep user_id null.
            $table->unique('user_id');
            $table->index('ofw_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['ofw_client_id']);
            $table->dropUnique(['user_id']);
            $table->dropIndex(['ofw_client_id']);
            $table->dropColumn(['type', 'user_id', 'ofw_client_id']);
        });
    }
};
