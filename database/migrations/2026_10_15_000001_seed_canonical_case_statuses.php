<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent insert: only insert missing slugs so re-running never duplicates.
        // Canonical case_statuses from 2026_06_01_000001_create_core_reference_tables.php
        // NOTE: query builder on purpose — the CaseStatus model fires cache
        // observers (Redis) that must not run inside migrations.
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
            if (! DB::table('case_statuses')->where('slug', $data['slug'])->exists()) {
                DB::table('case_statuses')->insert(array_merge($data, [
                    'id' => (string) Str::uuid(),
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        // Delete rows with the 7 canonical slugs where is_system=true
        DB::table('case_statuses')->whereIn('slug', [
            'OPEN', 'CLOSED', 'PENDING', 'PROCESSING',
            'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED',
        ])
            ->where('is_system', true)
            ->delete();
    }
};
