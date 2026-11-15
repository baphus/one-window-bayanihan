<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Services\AuditLogFormatter;
use Tests\TestCase;

class AuditLogFormatterCiphertextTest extends TestCase
{
    public function test_admin_changes_hide_base64_ciphertext_and_keep_missing_distinct_from_null(): void
    {
        $ciphertext = base64_encode(json_encode([
            'iv' => 'sensitive-iv',
            'value' => 'sensitive-plaintext-ciphertext',
            'mac' => 'sensitive-mac',
            'tag' => '',
        ]));
        $log = new AuditLog([
            'action' => 'UPDATE',
            'module' => 'CASE',
            'old_value' => ['case_number' => 'CASE-1', 'title' => null, 'tracker_number' => $ciphertext],
            'new_value' => ['title' => 'Set title', 'tracker_number' => $ciphertext],
            'timestamp' => now(),
        ]);

        $changes = collect((new AuditLogFormatter)->formatForAuditResponse($log, true)['changes'])->keyBy('field');

        $this->assertArrayNotHasKey('new', $changes['case_number']);
        $this->assertSame('CASE-1', $changes['case_number']['old']);
        $this->assertSame('Not set', $changes['title']['old']);
        $this->assertSame('Set title', $changes['title']['new']);
        $this->assertSame('Value unavailable (historical encrypted data)', $changes['tracker_number']['old']);
        $this->assertSame('Value unavailable (historical encrypted data)', $changes['tracker_number']['new']);
        $this->assertStringNotContainsString('sensitive-', json_encode($changes));

        $message = (new AuditLogFormatter)->formatForAuditResponse(new AuditLog([
            'action' => 'CREATE',
            'module' => 'CASE',
            'entity_label' => 'CASE-2026-001',
            'new_value' => [
                'case_number' => 'CASE-2026-001',
                'client' => ['first_name' => 'Ari', 'last_name' => 'Santos'],
                'category_names' => ['Contract Dispute'],
            ],
        ]), true)['message'];
        $this->assertStringContainsString('Case CASE-2026-001', $message);
        $this->assertStringContainsString('Ari Santos', $message);
    }
}
