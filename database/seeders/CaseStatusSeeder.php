<?php

namespace Database\Seeders;

use App\Models\CaseStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The canonical case_statuses vocabulary, for databases built by seeding.
 *
 * Mirrors database/migrations/2026_10_22_000001_seed_case_statuses_for_case_type.php
 * so a seeded environment and a migrated one agree. Production deploys run
 * `migrate` only, which is why the same rows also ship in a migration — this
 * seeder exists so `db:seed` produces a complete reference table rather than
 * one missing the lifecycle states.
 *
 * The cases-page filter panel reads type='case' rows for its Status group, and
 * the referrals page reads type='referral', so both sets must be present.
 *
 * DRAFT is intentionally absent: nothing writes status='DRAFT' (draft creation
 * is removed), and seeding it would offer a filter that can never match.
 */
class CaseStatusSeeder extends Seeder
{
    /**
     * @var array<int, array{slug: string, name: string, type: string, color: string, sort_order: int}>
     */
    private const STATUSES = [
        // Case lifecycle. OPEN/CLOSED were seeded by the squashed baseline;
        // ARCHIVED and PENDING_REVIEW are added here.
        ['slug' => 'OPEN', 'name' => 'Open', 'type' => 'case', 'color' => '#22c55e', 'sort_order' => 1],
        ['slug' => 'CLOSED', 'name' => 'Closed', 'type' => 'case', 'color' => '#64748b', 'sort_order' => 2],
        ['slug' => 'ARCHIVED', 'name' => 'Archived', 'type' => 'case', 'color' => '#94a3b8', 'sort_order' => 3],
        ['slug' => 'PENDING_REVIEW', 'name' => 'Pending Review', 'type' => 'case', 'color' => '#f59e0b', 'sort_order' => 4],

        // Referral lifecycle.
        ['slug' => 'PENDING', 'name' => 'Pending', 'type' => 'referral', 'color' => '#f59e0b', 'sort_order' => 1],
        ['slug' => 'PROCESSING', 'name' => 'Processing', 'type' => 'referral', 'color' => '#3b82f6', 'sort_order' => 2],
        ['slug' => 'FOR_COMPLIANCE', 'name' => 'For Compliance', 'type' => 'referral', 'color' => '#f97316', 'sort_order' => 3],
        ['slug' => 'COMPLETED', 'name' => 'Completed', 'type' => 'referral', 'color' => '#22c55e', 'sort_order' => 4],
        ['slug' => 'REJECTED', 'name' => 'Rejected', 'type' => 'referral', 'color' => '#ef4444', 'sort_order' => 5],
    ];

    public function run(): void
    {
        foreach (self::STATUSES as $status) {
            CaseStatus::firstOrCreate(
                ['slug' => $status['slug']],
                array_merge($status, [
                    'id' => (string) Str::uuid(),
                    'is_system' => true,
                    'is_active' => true,
                ]),
            );
        }
    }
}
