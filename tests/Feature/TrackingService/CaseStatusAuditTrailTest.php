<?php

namespace Tests\Feature\TrackingService;

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Services\AuditLogFormatter;
use App\Services\TrackingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class CaseStatusAuditTrailTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    /**
     * Eager-load all relationships that TrackingService::buildTrackingData() expects.
     */
    private function loadRelations(CaseFile $case): CaseFile
    {
        $case->load([
            'client.addresses',
            'client.employments',
            'referrals.agency',
            'referrals.milestones.user',
            'referrals.attachments',
            'user',
        ]);

        return $case;
    }

    /**
     * Verify that creating a CaseFile produces a CREATE audit log via the
     * AuditObserver, and that the log is queryable by entity_id with the
     * correct action and module.
     */
    public function test_case_creation_creates_audit_log(): void
    {
        // ARRANGE — createCompleteCase triggers AuditObserver::created()
        // which auto-generates a CREATE audit log for the CaseFile.
        $result = $this->createCompleteCase();
        $case = $result['case'];

        // ACT
        $logs = AuditLog::where('entity_id', $case->id)->get();

        // ASSERT
        $this->assertCount(1, $logs);
        $this->assertSame('CREATE', $logs[0]->action);
        $this->assertSame('case', $logs[0]->module);
        $this->assertEquals($case->id, $logs[0]->entity_id);
    }

    /**
     * Verify that a status-change UPDATE audit log correctly captures
     * the previous and new status values.
     */
    public function test_status_update_creates_audit_log(): void
    {
        // ARRANGE — case creation already created one CREATE audit log via observer
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $user = $result['user'];

        // Create a manual UPDATE log simulating OPEN transition
        $this->createAuditLog(
            entityId: $case->id,
            action: 'UPDATE',
            module: 'case_files',
            oldValue: ['status' => 'OPEN'],
            newValue: ['status' => 'OPEN'],
            userId: $user->id,
        );

        // ACT — lookup only UPDATE logs for this case
        $updateLogs = AuditLog::where('entity_id', $case->id)
            ->where('action', 'UPDATE')
            ->get();

        // ASSERT
        $this->assertCount(1, $updateLogs);
        $this->assertSame('OPEN', $updateLogs[0]->old_value['status']);
        $this->assertSame('OPEN', $updateLogs[0]->new_value['status']);
    }

    /**
     * Verify that an OPEN→CLOSED lifecycle produces audit logs in
     * correct chronological order with accurate old/new values.  Includes
     * the auto-created CREATE log from the observer plus two manually
     * created lifecycle logs.
     */
    public function test_full_lifecycle_audit_trail(): void
    {
        // ARRANGE — auto-created CREATE log exists (timestamp ≈ $base)
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $user = $result['user'];
        $base = Carbon::now();

        // Manually create 3 lifecycle audit logs with clearly distinguishable timestamps
        // Log 1: earliest — creation as OPEN
        AuditLog::create([
            'entity_id' => $case->id,
            'action' => 'CREATE',
            'module' => 'case_files',
            'new_value' => ['status' => 'OPEN', 'case_number' => $case->case_number],
            'user_id' => $user->id,
            'timestamp' => $base->copy()->subMinutes(10),
        ]);

        // Log 2: middle — OPEN → OPEN (no status change)
        AuditLog::create([
            'entity_id' => $case->id,
            'action' => 'UPDATE',
            'module' => 'case_files',
            'old_value' => ['status' => 'OPEN'],
            'new_value' => ['status' => 'OPEN'],
            'user_id' => $user->id,
            'timestamp' => $base->copy()->subMinutes(5),
        ]);

        // Log 3: latest — OPEN → CLOSED (after the auto-created log)
        AuditLog::create([
            'entity_id' => $case->id,
            'action' => 'UPDATE',
            'module' => 'case_files',
            'old_value' => ['status' => 'OPEN'],
            'new_value' => ['status' => 'CLOSED'],
            'user_id' => $user->id,
            'timestamp' => $base->copy()->addMinutes(5),
        ]);

        // ACT — ordered by timestamp
        $logs = AuditLog::where('entity_id', $case->id)
            ->orderBy('timestamp')
            ->get();

        // ASSERT — total count: 3 manual + 1 auto-created = 4
        $this->assertCount(4, $logs);

        // Entry 0 — earliest: manual CREATE OPEN
        $this->assertSame('CREATE', $logs[0]->action);
        $this->assertSame('OPEN', $logs[0]->new_value['status']);

        // Entry 1 — manual UPDATE OPEN → OPEN
        $this->assertSame('UPDATE', $logs[1]->action);
        $this->assertSame('OPEN', $logs[1]->old_value['status']);
        $this->assertSame('OPEN', $logs[1]->new_value['status']);

        // Entry 2 — auto-created CREATE (observer ran after factory create)
        $this->assertSame('CREATE', $logs[2]->action);
        $this->assertSame('case', $logs[2]->module);

        // Entry 3 — latest: manual UPDATE OPEN → CLOSED
        $this->assertSame('UPDATE', $logs[3]->action);
        $this->assertSame('OPEN', $logs[3]->old_value['status']);
        $this->assertSame('CLOSED', $logs[3]->new_value['status']);

        // Verify timestamps strictly increase throughout
        $this->assertTrue($logs[0]->timestamp->lt($logs[1]->timestamp));
        $this->assertTrue($logs[1]->timestamp->lt($logs[2]->timestamp));
        $this->assertTrue($logs[2]->timestamp->lt($logs[3]->timestamp));
    }

    /**
     * Verify that AuditLogFormatter produces human-readable messages
     * without UUIDs, raw internal field names, or database column names.
     */
    public function test_human_readable_audit_messages(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $user = $result['user'];

        // CREATE log with case details for the human-readable template
        $createLog = AuditLog::create([
            'entity_id' => $case->id,
            'action' => 'CREATE',
            'module' => 'case_files',
            'new_value' => [
                'status' => 'OPEN',
                'case_number' => $case->case_number,
                'client_type' => $case->client_type,
            ],
            'user_id' => $user->id,
            'timestamp' => now(),
        ]);

        // UPDATE log: OPEN → OPEN
        $updateLog = AuditLog::create([
            'entity_id' => $case->id,
            'action' => 'UPDATE',
            'module' => 'case_files',
            'old_value' => ['status' => 'OPEN', 'case_number' => $case->case_number],
            'new_value' => ['status' => 'OPEN', 'case_number' => $case->case_number],
            'user_id' => $user->id,
            'timestamp' => now(),
        ]);

        $formatter = app(AuditLogFormatter::class);

        // ACT
        $createMessage = $formatter->format($createLog);
        $updateMessage = $formatter->format($updateLog);

        // ASSERT — CREATE: should read like "Case CASE-20260622-1234 opened for OFW client"
        $this->assertStringContainsString('Case', $createMessage);
        $this->assertStringContainsString($case->case_number, $createMessage);
        // No UUIDs (full UUID pattern with hyphens)
        $this->assertDoesNotMatchRegularExpression(
            '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/',
            $createMessage
        );
        // No internal field/column names
        $this->assertStringNotContainsString('old_value', $createMessage);
        $this->assertStringNotContainsString('new_value', $createMessage);
        $this->assertStringNotContainsString('tracker_number', $createMessage);

        // ASSERT — UPDATE: should read like "CASE-20260622-1234 status changed to Open"
        $this->assertStringContainsString($case->case_number, $updateMessage);
        $this->assertStringContainsStringIgnoringCase('status', $updateMessage);
        $this->assertStringContainsString('Open', $updateMessage);
        // No UUIDs
        $this->assertDoesNotMatchRegularExpression(
            '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/',
            $updateMessage
        );
        // No raw field/column names
        $this->assertStringNotContainsString('old_value', $updateMessage);
        $this->assertStringNotContainsString('new_value', $updateMessage);
        $this->assertStringNotContainsString('tracker_number', $updateMessage);
    }

    /**
     * The public tracking payload must NOT expose audit-log content. Audit
     * logs remain an internal-only record; the client-facing history comes
     * exclusively from case_events (milestoneTimeline).
     */
    public function test_audit_trail_not_exposed_in_tracking_data(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $user = $result['user'];

        // Manually add 2 audit logs (in addition to the auto-created one
        // from the AuditObserver)
        AuditLog::create([
            'entity_id' => $case->id,
            'action' => 'CREATE',
            'module' => 'case_files',
            'new_value' => [
                'status' => 'OPEN',
                'case_number' => $case->case_number,
                'client_type' => $case->client_type,
            ],
            'user_id' => $user->id,
            'timestamp' => now()->subMinutes(10),
        ]);

        AuditLog::create([
            'entity_id' => $case->id,
            'action' => 'UPDATE',
            'module' => 'case_files',
            'old_value' => ['status' => 'OPEN', 'case_number' => $case->case_number],
            'new_value' => ['status' => 'OPEN', 'case_number' => $case->case_number],
            'user_id' => $user->id,
            'timestamp' => now()->subMinutes(5),
        ]);

        $service = app(TrackingService::class);

        // ACT
        $this->loadRelations($case);
        $data = $service->buildTrackingData($case);

        // ASSERT — the legacy audit-log timeline is gone from the payload
        $this->assertArrayNotHasKey('caseTimeline', $data);

        // The audit logs still exist internally — they just never reach
        // the public payload.
        $this->assertGreaterThanOrEqual(2, AuditLog::where('entity_id', $case->id)->count());

        // Nothing in the payload may carry audit-log vocabulary or the
        // acting user's name.
        $payload = json_encode($data);
        $this->assertStringNotContainsString('old_value', $payload);
        $this->assertStringNotContainsString('new_value', $payload);
        $this->assertStringNotContainsString($user->name, $payload);
    }

    /**
     * Verify that a case with no explicit status changes has exactly one
     * CREATE audit log (from the observer) and no extraneous UPDATE logs.
     */
    public function test_no_orphan_audit_logs(): void
    {
        // ARRANGE — create a case but do NOT make any status changes or
        // manually create any additional audit logs.
        // The AuditObserver::created() will fire exactly once.
        $result = $this->createCompleteCase();
        $case = $result['case'];

        // ACT
        $allCaseLogs = AuditLog::where('entity_id', $case->id)->get();
        $updateLogs = AuditLog::where('entity_id', $case->id)
            ->where('action', 'UPDATE')
            ->get();

        // ASSERT
        $this->assertCount(1, $allCaseLogs);
        $this->assertSame('CREATE', $allCaseLogs[0]->action);
        $this->assertCount(0, $updateLogs);
    }
}
