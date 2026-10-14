<?php

use App\Models\CaseStatus;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent insert: only insert missing slugs so re-running never duplicates.
        // Canonical case_statuses from 2026_06_01_000001_create_core_reference_tables.php
        $canonicals = [
            ['slug' => 'OPEN',     'name' => 'Open',           'type' => 'case',     'color' => '#22c55e', 'sort_order' => 1],
            ['slug' => 'CLOSED',   'name' => 'Closed',         'type' => 'case',     'color' => '#64748b', 'sort_order' => 2],
            ['slug' => 'PENDING',  'name' => 'Pending',        'type' => 'referral', 'color' => '#f59e0b', 'sort_order' => 1],
            ['slug' => 'PROCESSING', 'name' => 'Processing',   'type' => 'referral', 'color' => '#3b82f6', 'sort_order' => 2],
            ['slug' => 'FOR_COMPLIANCE', 'name' => 'For Compliance', 'type' => 'referral', 'color' => '#f97316', 'sort_order' => 3],
            ['slug' => 'COMPLETED',  'name' => 'Completed',      'type' => 'referral', 'color' => '#22c55e', 'sort_order' => 4],
            ['slug' => 'REJECTED',   'name' => 'Rejected',       'type' => 'referral', 'color' => '#ef4444', 'sort_order' => 5],
        ];

        foreach ($canonicals as $data) {
            CaseStatus::where('slug', $data['slug'])
                ->firstOrCreate(
                    ['slug' => $data['slug']],
                    array_merge($data, ['is_system' => true, 'is_active' => true])
                );
        }
    }

    public function down(): void
    {
        // Delete rows with the 7 canonical slugs where is_system=true
        CaseStatus::whereIn('slug', [
            'OPEN', 'CLOSED', 'PENDING', 'PROCESSING',
            'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED',
        ])
            ->where('is_system', true)
            ->delete();
    }
};
