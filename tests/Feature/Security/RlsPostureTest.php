<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pins the RLS posture recorded in docs/security/rls-posture-decision-v1.0.0.md
 * (finding 12, Option B — honest documentation).
 *
 * The point of this test is that the *documented* posture and the *database*
 * posture cannot drift apart silently. If someone forces RLS on the core
 * tables, or drops the FORCE flag from the inbox tables, this fails and the
 * decision record has to be updated in the same change.
 */
class RlsPostureTest extends TestCase
{
    /**
     * Tables where RLS is FORCEd, so the policies bind every role including
     * the table owner. Isolation here is real at the DB layer.
     *
     * @var list<string>
     */
    private const FORCED_TABLES = [
        'agency_thread_reads',
        'referral_messages',
        'referral_client_requests',
        'referral_client_request_items',
        'referral_client_messages',
        'referral_client_message_attachments',
        'referral_client_access_links',
    ];

    /**
     * Tables where RLS is merely enabled. The runtime owns these tables, so
     * the policies are bypassed and isolation is application-level only.
     *
     * @var list<string>
     */
    private const ENABLED_NOT_FORCED_TABLES = [
        'cases',
        'clients',
        'client_addresses',
        'client_employments',
        'next_of_kin',
        'referrals',
        'milestones',
        'referral_attachments',
        'referral_comments',
        'case_documents',
        'case_notifications',
    ];

    private const DECISION_DOC = 'docs/security/rls-posture-decision-v1.0.0.md';

    public function test_forced_tables_force_rls(): void
    {
        foreach (self::FORCED_TABLES as $table) {
            $row = $this->rlsState($table);
            $this->assertTrue((bool) $row->relrowsecurity, $table.' should have RLS enabled');
            $this->assertTrue((bool) $row->relforcerowsecurity, $table.' should FORCE RLS');
        }
    }

    public function test_core_pii_tables_enable_rls_but_do_not_force_it(): void
    {
        foreach (self::ENABLED_NOT_FORCED_TABLES as $table) {
            $row = $this->rlsState($table);
            $this->assertTrue((bool) $row->relrowsecurity, $table.' should have RLS enabled');
            $this->assertFalse(
                (bool) $row->relforcerowsecurity,
                $table.' must not FORCE RLS while the runtime owns the table — the policies would be bypassed anyway, '
                .'so forcing it is a schema change that has to be recorded in '.self::DECISION_DOC
            );
        }
    }

    /**
     * The decision record is the authoritative statement of posture, so it must
     * name every table in each bucket. This is the drift guard: a table added to
     * one of the arrays above without a matching doc edit fails here.
     */
    public function test_decision_record_lists_every_table_in_both_buckets(): void
    {
        $path = base_path(self::DECISION_DOC);
        $this->assertFileExists($path);

        $doc = file_get_contents($path);

        foreach ([...self::FORCED_TABLES, ...self::ENABLED_NOT_FORCED_TABLES] as $table) {
            $this->assertStringContainsString(
                '`'.$table.'`',
                $doc,
                "RLS decision record must name `{$table}` so the documented posture matches the schema"
            );
        }
    }

    /**
     * The enabled-but-not-forced tables must still carry policies — the posture
     * is "policies present, enforcement latent", not "no policies". If the
     * policies were dropped, forcing RLS later would deny everything, and the
     * deferred Option A would be far more work than documented.
     */
    public function test_core_tables_retain_policies_for_a_future_enforcement_switch(): void
    {
        foreach (self::ENABLED_NOT_FORCED_TABLES as $table) {
            $count = DB::table('pg_policies')->where('tablename', $table)->count();
            $this->assertGreaterThan(
                0,
                $count,
                $table.' should keep its policies so the posture can be switched to enforced without rewriting them'
            );
        }
    }

    private function rlsState(string $table): object
    {
        $row = DB::selectOne(
            'SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE oid = ?::regclass',
            [$table]
        );

        $this->assertNotNull($row, $table.' should exist');

        return $row;
    }
}
