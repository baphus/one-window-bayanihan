<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->integer('sla_target_days')->default(30)->after('user_id');
            $table->boolean('sla_met')->nullable()->after('sla_target_days');
            $table->timestamp('escalated_at')->nullable()->after('sla_target_days');
            $table->string('escalation_reason')->nullable()->after('sla_target_days');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->timestamp('first_action_at')->nullable()->after('decision');
            $table->timestamp('referral_assigned_at')->nullable()->after('first_action_at');
            $table->integer('sla_target_days')->default(14)->after('first_action_at');
            $table->boolean('sla_met')->nullable()->after('first_action_at');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['sla_target_days', 'sla_met', 'escalated_at', 'escalation_reason']);
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn(['first_action_at', 'referral_assigned_at', 'sla_target_days', 'sla_met']);
        });
    }
};
