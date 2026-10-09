<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_invites', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('token')->nullable()->change();
        });

        DB::table('user_invites')->whereNotNull('token')->whereNull('token_hash')->orderBy('id')->chunkById(500, function ($invites): void {
            foreach ($invites as $invite) {
                DB::table('user_invites')->where('id', $invite->id)->update([
                    'token_hash' => hash('sha256', $invite->token),
                ]);
            }
        }, 'id');
    }

    public function down(): void
    {
        Schema::table('user_invites', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
            $table->dropColumn('token_hash');
            // Legacy plaintext column intentionally left nullable; do not restore NOT NULL here.
        });
    }
};
