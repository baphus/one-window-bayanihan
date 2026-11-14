<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Schema dumps omit reference rows; fresh installs still need valid status filters.
        $statuses = [
            ['OPEN', 'Open', 'case', '#22c55e', 1],
            ['CLOSED', 'Closed', 'case', '#64748b', 2],
            ['ARCHIVED', 'Archived', 'case', '#94a3b8', 3],
            ['PENDING_REVIEW', 'Pending Review', 'case', '#f59e0b', 4],
            ['PENDING', 'Pending', 'referral', '#f59e0b', 1],
            ['PROCESSING', 'Processing', 'referral', '#3b82f6', 2],
            ['FOR_COMPLIANCE', 'For Compliance', 'referral', '#f97316', 3],
            ['COMPLETED', 'Completed', 'referral', '#22c55e', 4],
            ['REJECTED', 'Rejected', 'referral', '#ef4444', 5],
        ];
        foreach ($statuses as [$slug, $name, $type, $color, $order]) {
            if (! DB::table('case_statuses')->where('slug', $slug)->exists()) {
                DB::table('case_statuses')->insert([
                    'id' => (string) Str::uuid(), 'slug' => $slug, 'name' => $name,
                    'type' => $type, 'color' => $color, 'sort_order' => $order,
                    'is_system' => true, 'is_active' => true, 'is_deleted' => false,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Reference vocabulary may already be in use; rollback must not remove it.
    }
};
