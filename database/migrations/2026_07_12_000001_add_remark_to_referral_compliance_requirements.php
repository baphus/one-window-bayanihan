<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_compliance_requirements', function (Blueprint $table) {
            $table->text('remark')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('referral_compliance_requirements', function (Blueprint $table) {
            $table->dropColumn('remark');
        });
    }
};
