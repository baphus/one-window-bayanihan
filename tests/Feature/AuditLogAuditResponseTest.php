<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Services\AuditLogFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Contracts for the live audit response (App\Services\AuditLogFormatter
 * ::formatForAuditResponse): entity identification in the message and the
 * after-only safe changes payload, with the unclassified-guardrail preserved.
 */
class AuditLogAuditResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_response_identifies_case_number_and_after_only_changes(): void
    {
        $case = CaseFile::factory()->create();
        $log = new AuditLog([
            'action' => 'UPDATE',
            'module' => 'case_files',
            'entity_id' => $case->id,
            'old_value' => ['status' => 'OPEN', 'summary' => 'old'],
            'new_value' => ['status' => 'CLOSED', 'summary' => 'new summary', 'closed_at' => '2026-08-30 15:30:00'],
            'timestamp' => now(),
        ]);

        $response = (new AuditLogFormatter)->formatForAuditResponse($log);

        $this->assertStringContainsString('Case ', $response['message']);
        $this->assertStringContainsString($case->case_number, $response['message']);

        $this->assertSame(
            [['field' => 'status', 'fieldLabel' => 'status', 'new' => 'Closed']],
            $response['changes'],
        );
        $this->assertArrayNotHasKey('old', $response['changes'][0]);
        // Date-only lifecycle fields are redundant with the entry timestamp.
        $this->assertNotContains('closed_at', array_column($response['changes'], 'field'));

        // The raw entity UUID must never be echoed back.
        $this->assertStringNotContainsString($case->id, json_encode($response));

        $this->assertDoesNotMatchRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $response['message'],
        );
    }

    public function test_zip_download_message_uses_only_case_label_and_valid_document_count(): void
    {
        foreach ([1 => ' — 1 document', 2 => ' — 2 documents', 0 => ''] as $count => $suffix) {
            $log = new AuditLog([
                'action' => 'DOWNLOAD',
                'module' => 'case_document',
                'entity_id' => Str::uuid()->toString(),
                'entity_label' => 'OWB-2026-1234',
                'new_value' => ['format' => 'zip', 'scope' => 'case_documents', 'count' => $count],
            ]);

            $response = (new AuditLogFormatter)->formatForAuditResponse($log);

            $this->assertSame('System downloaded case documents ZIP for Case OWB-2026-1234'.$suffix, $response['message']);
        }
    }

    public function test_audit_response_never_renders_a_referral_id(): void
    {
        $entityId = Str::uuid()->toString();
        $log = new AuditLog([
            'action' => 'UPDATE',
            'module' => 'referral',
            'entity_id' => $entityId,
            'new_value' => ['status' => 'COMPLETED'],
            'timestamp' => now(),
        ]);

        $response = (new AuditLogFormatter)->formatForAuditResponse($log);

        $this->assertSame('System updated a referral', $response['message']);
        $this->assertStringNotContainsString(substr($entityId, 0, 8), $response['message']);
        $this->assertStringNotContainsString($entityId, json_encode($response));
        $this->assertSame(
            [['field' => 'status', 'fieldLabel' => 'status', 'new' => 'Completed']],
            $response['changes'],
        );
    }

    public function test_audit_response_never_renders_a_milestone_id(): void
    {
        $entityId = Str::uuid()->toString();
        $log = new AuditLog([
            'action' => 'UPDATE',
            'module' => 'milestone',
            'entity_id' => $entityId,
            'new_value' => ['status' => 'COMPLETED'],
            'timestamp' => now(),
        ]);

        $response = (new AuditLogFormatter)->formatForAuditResponse($log);

        $this->assertSame('System updated a milestone', $response['message']);
        $this->assertStringNotContainsString(substr($entityId, 0, 8), $response['message']);
        $this->assertStringNotContainsString($entityId, json_encode($response));
    }

    public function test_audit_response_guardrail_holds_for_unclassified_stored_text(): void
    {
        $sentinel = 'private-value-must-not-reach-audit-responses';
        $log = new AuditLog([
            'action' => 'WEIRD',
            'module' => 'not_a_real_module',
            'entity_id' => Str::uuid()->toString(),
            'old_value' => ['private_note' => 'previous private note'],
            'new_value' => ['private_note' => $sentinel],
            'timestamp' => now(),
        ]);

        $response = (new AuditLogFormatter)->formatForAuditResponse($log);

        $this->assertSame('UNKNOWN', $response['action']);
        $this->assertSame('An unclassified activity was recorded', $response['message']);
        $this->assertSame([], $response['changes']);
        $this->assertStringNotContainsString($sentinel, json_encode($response));
    }
}
