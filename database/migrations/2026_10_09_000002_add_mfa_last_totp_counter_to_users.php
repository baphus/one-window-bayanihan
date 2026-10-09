<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable record of the last accepted TOTP counter.
 *
 * MfaService::verifyTotp() used to block a replayed code with a cache key
 * alone. That guard disappeared on any cache:clear, Redis restart or a server
 * whose cache driver is per-request, so a code captured in transit stayed
 * usable indefinitely. The cache stays as the fast path; this column is what
 * survives losing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('mfa_last_totp_counter')->nullable()->after('mfa_enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mfa_last_totp_counter');
        });
    }
};
