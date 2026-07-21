<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the standardised audit vocabulary: the AuditAction enum must stay in
 * lock-step with the database CHECK constraint, unknown actions must be
 * rejected, and the frozen tamper-evidence digest must not drift.
 */
class AuditVocabularyTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_action_enum_matches_database_check_constraint(): void
    {
        $def = DB::selectOne(
            "SELECT pg_get_constraintdef(oid) AS def
             FROM pg_constraint
             WHERE conname = 'audit_logs_action_check'"
        );

        $this->assertNotNull($def, 'audit_logs_action_check constraint is missing');

        preg_match_all("/'([A-Z_]+)'/", $def->def, $matches);
        $dbActions = collect($matches[1])->unique()->sort()->values()->all();
        $enumActions = collect(AuditAction::values())->sort()->values()->all();

        $this->assertSame(
            $enumActions,
            $dbActions,
            'AuditAction enum and the DB CHECK constraint have diverged.'
        );
    }

    public function test_unknown_action_is_rejected_before_persistence(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AuditLog::create([
            'action' => 'FROBNICATE',
            'module' => AuditModule::USER->value,
            'timestamp' => now(),
        ]);
    }

    public function test_every_enum_action_is_accepted(): void
    {
        foreach (AuditAction::cases() as $action) {
            AuditLog::create([
                'action' => $action->value,
                'module' => AuditModule::AUDIT->value,
                'timestamp' => now(),
            ]);
        }

        $this->assertSame(count(AuditAction::cases()), AuditLog::count());
    }

    /**
     * The chain digest is frozen: its field list and serialisation feed the
     * tamper-evidence hash of every existing row. This pins the exact bytes so
     * any accidental change (e.g. casting action/module to an enum object) is
     * caught immediately.
     */
    public function test_chain_digest_serialisation_is_frozen(): void
    {
        $log = new AuditLog([
            'action' => 'CREATE',
            'module' => 'case',
            'entity_id' => '00000000-0000-0000-0000-000000000002',
            'user_id' => '00000000-0000-0000-0000-000000000003',
            'timestamp' => Carbon::parse('2026-01-01T00:00:00+00:00'),
            'old_value' => null,
            'new_value' => ['case_number' => 'C-1'],
            'ip_address' => '127.0.0.1',
            'prev_hash' => null,
        ]);
        // id is not mass-assignable; set it directly so the digest is deterministic.
        $log->id = '00000000-0000-0000-0000-000000000001';

        $this->assertSame(
            '5acd00a47de001150eb773e23e5ef895e1f0052f3d7eddea89e026f50b702194',
            $log->chainDigest(),
        );
    }
}
