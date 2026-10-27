<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds the two case-lifecycle statuses the query layer already uses but which
 * were never present in `case_statuses`: ARCHIVED and PENDING_REVIEW.
 *
 * `cases.status` is a free varchar with no FK, so the PHP code — not this
 * table — has always been the de facto vocabulary. The cases-page filter panel
 * reads its options from `case_statuses`, so without these rows the Archived
 * pill and the intake state would have no label. DRAFT is deliberately absent:
 * nothing writes it (draft creation is being removed), so seeding it would
 * advertise a status no case can hold.
 *
 * Deploys run `migrate` only — never `db:seed` — so reference rows must ship
 * in a migration (see tests/Feature/Database/MigrationConventionTest.php).
 * CaseStatusSeeder mirrors these rows for databases built by seeding.
 *
 * Query builder on purpose: the CaseStatus model fires cache observers that
 * must not run inside migrations.
 */
return new class extends Migration
{
    /**
     * Slug => attributes. Order is significant only for display (sort_order).
     *
     * @var array<string, array{name: string, type: string, color: string, sort_order: int}>
     */
    private const STATUSES = [
        'ARCHIVED' => ['name' => 'Archived', 'type' => 'case', 'color' => '#94a3b8', 'sort_order' => 3],
        'PENDING_REVIEW' => ['name' => 'Pending Review', 'type' => 'case', 'color' => '#f59e0b', 'sort_order' => 4],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::STATUSES as $slug => $attributes) {
            // Idempotent: an environment that already has the row (seeded by
            // CaseStatusSeeder, or hand-created by an admin) keeps its values.
            if (DB::table('case_statuses')->where('slug', $slug)->exists()) {
                continue;
            }

            DB::table('case_statuses')->insert(array_merge($attributes, [
                'id' => (string) Str::uuid(),
                'slug' => $slug,
                'is_system' => true,
                'is_active' => true,
                'is_deleted' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        // Only remove rows this migration owns: a system row with these slugs.
        // An admin-created (non-system) row of the same slug survives.
        DB::table('case_statuses')
            ->whereIn('slug', array_keys(self::STATUSES))
            ->where('is_system', true)
            ->delete();
    }
};
