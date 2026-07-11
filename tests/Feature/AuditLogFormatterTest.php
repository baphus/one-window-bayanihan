<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogFormatter;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditLogFormatterTest extends TestCase
{
    public function test_it_generates_create_description(): void
    {
        $log = new AuditLog([
            'action' => 'CREATE',
            'module' => 'case_files',
            'new_value' => ['case_number' => 'CAS-001', 'client_type' => 'OFW'],
            'user_id' => null,
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $result = $formatter->format($log);

        $this->assertStringContainsString('opened', $result);
        $this->assertStringContainsString('CAS-001', $result);
        $this->assertStringContainsString('OFW', $result);
    }

    public function test_it_generates_update_description_with_changes(): void
    {
        $log = new AuditLog([
            'action' => 'UPDATE',
            'module' => 'case_files',
            'old_value' => ['status' => 'PROCESSING'],
            'new_value' => ['status' => 'COMPLETED'],
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $result = $formatter->format($log);

        $this->assertStringContainsString('Case status changed to', $result);
        $this->assertStringContainsString('Completed', $result);
    }

    public function test_it_generates_delete_description(): void
    {
        $log = new AuditLog([
            'action' => 'DELETE',
            'module' => 'agencies',
            'old_value' => ['name' => 'Test Agency'],
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $result = $formatter->format($log);

        $this->assertStringContainsString('removed from the system', $result);
        $this->assertStringContainsString('Test Agency', $result);
    }

    public function test_it_handles_login_action(): void
    {
        $log = new AuditLog([
            'action' => 'LOGIN',
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $result = $formatter->format($log);

        $this->assertStringContainsString('signed in on', $result);
        $this->assertStringContainsString('at', $result);
    }

    public function test_it_handles_logout_action(): void
    {
        $log = new AuditLog([
            'action' => 'LOGOUT',
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $result = $formatter->format($log);

        $this->assertStringContainsString('signed out', $result);
    }

    public function test_it_uses_existing_description_if_set(): void
    {
        $log = new AuditLog([
            'description' => 'Cached description',
            'action' => 'CREATE',
            'module' => 'case_files',
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $this->assertEquals('Cached description', $formatter->format($log));
    }

    public function test_it_returns_system_for_null_user(): void
    {
        $log = new AuditLog([
            'action' => 'CREATE',
            'module' => 'case_files',
            'new_value' => ['case_number' => 'CAS-001'],
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $result = $formatter->format($log);

        $this->assertStringContainsString('CAS-001', $result);
    }

    public function test_it_handles_multiple_field_changes(): void
    {
        $log = new AuditLog([
            'action' => 'UPDATE',
            'module' => 'case_files',
            'old_value' => ['status' => 'PROCESSING', 'is_active' => false],
            'new_value' => ['status' => 'COMPLETED', 'is_active' => true],
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $result = $formatter->format($log);

        $this->assertStringContainsString('Case updated:', $result);
        $this->assertStringContainsString('status', $result);
        $this->assertStringContainsString('active status', $result);
    }

    #[DataProvider('actionProvider')]
    public function test_format_action_mapping(string $action, string $expected): void
    {
        $formatter = new AuditLogFormatter;

        $this->assertEquals($expected, $formatter->formatAction($action));
    }

    #[DataProvider('moduleProvider')]
    public function test_format_module_mapping(string $module, string $expected): void
    {
        $formatter = new AuditLogFormatter;

        $this->assertEquals($expected, $formatter->formatModule($module));
    }

    #[DataProvider('fieldNameProvider')]
    public function test_format_field_name_mapping(string $field, string $expected): void
    {
        $formatter = new AuditLogFormatter;

        $this->assertEquals($expected, $formatter->formatFieldName($field));
    }

    #[DataProvider('fieldValueProvider')]
    public function test_format_field_value_mapping(string $module, string $field, mixed $value, string $expected): void
    {
        $formatter = new AuditLogFormatter;

        $this->assertEquals($expected, $formatter->formatFieldValue($module, $field, $value));
    }

    public function test_it_generates_user_registration(): void
    {
        $log = new AuditLog([
            'action' => 'CREATE',
            'module' => 'users',
            'new_value' => ['name' => 'Doug Aufderhar', 'role' => 'CASE_MANAGER'],
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;
        $result = $formatter->format($log);

        $this->assertStringContainsString('Doug Aufderhar registered as', $result);
        $this->assertStringContainsString('Case Manager', $result);
    }

    public function test_format_for_display_returns_structured_data(): void
    {
        $log = new AuditLog([
            'action' => 'CREATE',
            'module' => 'case_files',
            'new_value' => ['case_number' => 'CAS-001', 'client_type' => 'OFW'],
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;
        $display = $formatter->formatForDisplay($log);

        $this->assertArrayHasKey('message', $display);
        $this->assertArrayHasKey('detail', $display);
        $this->assertArrayHasKey('changes', $display);
        $this->assertArrayHasKey('action', $display);
        $this->assertArrayHasKey('module', $display);
        $this->assertArrayHasKey('actor', $display);
        $this->assertArrayHasKey('hasChanges', $display);
        $this->assertEquals('CREATE', $display['action']);
        $this->assertSame('', $display['detail']);
        $this->assertIsArray($display['changes']);
        $this->assertTrue($display['hasChanges']);
    }

    public function test_get_structured_changes_basic_field_change(): void
    {
        $formatter = new AuditLogFormatter;

        $changes = $formatter->getStructuredChanges(
            ['status' => 'OPEN'],
            ['status' => 'CLOSED'],
        );

        $this->assertCount(1, $changes);
        $this->assertSame('status', $changes[0]['field']);
        $this->assertSame('status', $changes[0]['fieldLabel']);
        $this->assertSame('Open', $changes[0]['old']);
        $this->assertSame('Closed', $changes[0]['new']);
    }

    public function test_get_structured_changes_multiple_fields(): void
    {
        $formatter = new AuditLogFormatter;

        $changes = $formatter->getStructuredChanges(
            ['status' => 'PROCESSING', 'priority' => 'LOW'],
            ['status' => 'COMPLETED', 'priority' => 'HIGH'],
        );

        $this->assertCount(2, $changes);
        $this->assertSame('status', $changes[0]['field']);
        $this->assertSame('Completed', $changes[0]['new']);
        $this->assertSame('priority', $changes[1]['field']);
        $this->assertSame('HIGH', $changes[1]['new']);
    }

    public function test_get_structured_changes_null_old(): void
    {
        $formatter = new AuditLogFormatter;

        $changes = $formatter->getStructuredChanges(
            null,
            ['status' => 'OPEN', 'client_type' => 'OFW'],
        );

        // When old is null, all non-noise fields appear (like CREATE)
        $this->assertCount(2, $changes);
        $this->assertSame('status', $changes[0]['field']);
        $this->assertNull($changes[0]['old']);
        $this->assertSame('Open', $changes[0]['new']);
    }

    public function test_get_structured_changes_null_new(): void
    {
        $formatter = new AuditLogFormatter;

        $changes = $formatter->getStructuredChanges(
            ['status' => 'OPEN'],
            null,
        );

        // When new is null, all non-noise fields appear (like DELETE)
        $this->assertCount(1, $changes);
        $this->assertSame('status', $changes[0]['field']);
        $this->assertSame('Open', $changes[0]['old']);
        $this->assertNull($changes[0]['new']);
    }

    public function test_get_structured_changes_create_noise_filtering(): void
    {
        $formatter = new AuditLogFormatter;

        $changes = $formatter->getStructuredChanges(
            null,
            [
                'case_number' => 'CAS-001',
                'tracker_number' => 'TRK-001',
                'consent_given_at' => '2026-01-01',
                'status' => 'OPEN',
                'client_type' => 'OFW',
            ],
            'CREATE',
        );

        // case_number, tracker_number, consent_given_at, status filtered by CREATE noise
        // Only client_type remains
        $this->assertCount(1, $changes);
        $this->assertSame('client_type', $changes[0]['field']);
    }

    public function test_get_structured_changes_draft_client_data(): void
    {
        $formatter = new AuditLogFormatter;

        $changes = $formatter->getStructuredChanges(
            null,
            [
                'draft_client_data' => [
                    'first_name' => 'Juan',
                    'last_name' => 'Dela Cruz',
                    'email' => 'juan@example.com',
                    'client_type' => 'OFW',
                ],
            ],
            'CREATE',
        );

        $this->assertCount(1, $changes);
        $this->assertSame('draft_client_data', $changes[0]['field']);
        $this->assertStringContainsString('Juan Dela Cruz', $changes[0]['new']);
        $this->assertStringContainsString('OFW', $changes[0]['new']);
        $this->assertStringContainsString('juan@example.com', $changes[0]['new']);
    }

    public function test_get_structured_changes_uuid_field_unresolved(): void
    {
        $formatter = new AuditLogFormatter;

        $changes = $formatter->getStructuredChanges(
            null,
            ['user_id' => '00000000-0000-0000-0000-000000000000'],
            'CREATE',
        );

        // user_id is not in noise fields, so it should appear
        $this->assertCount(1, $changes);
        $this->assertSame('user_id', $changes[0]['field']);
        $this->assertSame('assigned user', $changes[0]['fieldLabel']);
        // No matching user exists, so raw value passes through
        $this->assertSame('00000000-0000-0000-0000-000000000000', $changes[0]['new']);
    }

    public function test_get_structured_changes_both_null(): void
    {
        $formatter = new AuditLogFormatter;

        $changes = $formatter->getStructuredChanges(null, null);

        $this->assertSame([], $changes);
    }

    public function test_get_structured_changes_skips_noise_fields(): void
    {
        $formatter = new AuditLogFormatter;

        $changes = $formatter->getStructuredChanges(
            ['id' => 'old-uuid', 'name' => 'Old Name'],
            ['id' => 'new-uuid', 'name' => 'New Name'],
        );

        // id should be skipped (noise), name should appear
        $this->assertCount(1, $changes);
        $this->assertSame('name', $changes[0]['field']);
    }

    public function test_it_uses_loaded_user_name_in_login_when_available(): void
    {
        $log = new AuditLog([
            'action' => 'LOGIN',
            'module' => 'auth',
            'timestamp' => now(),
        ]);

        $log->setRelation('user', new User(['name' => 'Maria Santos']));

        $formatter = new AuditLogFormatter;

        $this->assertStringContainsString('Maria Santos signed in', $formatter->format($log));
    }

    public function test_format_create_works_with_new_module_name(): void
    {
        $log = new AuditLog([
            'action' => 'CREATE',
            'module' => 'case',
            'new_value' => ['case_number' => 'CAS-001', 'client_type' => 'OFW'],
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $result = $formatter->format($log);

        $this->assertStringContainsString('opened', $result);
        $this->assertStringContainsString('CAS-001', $result);
    }

    public function test_format_update_works_with_new_module_name(): void
    {
        $log = new AuditLog([
            'action' => 'UPDATE',
            'module' => 'case',
            'old_value' => ['status' => 'PROCESSING'],
            'new_value' => ['status' => 'COMPLETED'],
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $result = $formatter->format($log);

        $this->assertStringContainsString('Case status changed to', $result);
        $this->assertStringContainsString('Completed', $result);
    }

    #[DataProvider('loginTimezoneProvider')]
    public function test_it_formats_login_timezone(Carbon $timestamp, string $timezone, string $expected): void
    {
        $log = new AuditLog([
            'action' => 'LOGIN',
            'timestamp' => $timestamp,
        ]);

        $log->setRelation('user', new User(['name' => 'Test User', 'timezone' => $timezone]));

        $formatter = new AuditLogFormatter;
        $result = $formatter->format($log);

        $this->assertStringContainsString($expected, $result);
    }

    public function test_it_falls_back_to_asia_manila_timezone(): void
    {
        $log = new AuditLog([
            'action' => 'LOGIN',
            'timestamp' => '2026-06-02 12:29:00',
        ]);

        $formatter = new AuditLogFormatter;
        $result = $formatter->format($log);

        $this->assertStringContainsString('signed in on', $result);
        $this->assertStringContainsString('at', $result);
    }

    public static function actionProvider(): array
    {
        return [
            ['CREATE', 'created'],
            ['UPDATE', 'updated'],
            ['DELETE', 'deleted'],
            ['LOGIN', 'signed in'],
            ['LOGOUT', 'signed out'],
        ];
    }

    public static function moduleProvider(): array
    {
        return [
            ['case_files', 'Case'],
            ['clients', 'Client'],
            ['referrals', 'Referral'],
            ['agencies', 'Agency'],
            ['users', 'User'],
            ['services', 'Service'],
            ['client_addresses', 'Address'],
            ['client_employments', 'Employment Record'],
            ['milestones', 'Milestone'],
            ['referral_attachments', 'Attachment'],
            // New singular/modern module names
            ['agency', 'Agency'],
            ['case', 'Case'],
            ['cases', 'Case'],
            ['client', 'Client'],
            ['client_address', 'Address'],
            ['client_employment', 'Employment Record'],
            ['milestone', 'Milestone'],
            ['referral', 'Referral'],
            ['referral_attachment', 'Attachment'],
            ['service', 'Service'],
            ['user', 'User'],
        ];
    }

    public static function fieldNameProvider(): array
    {
        return [
            ['status', 'status'],
            ['case_number', 'case number'],
            ['first_name', 'first name'],
            ['email', 'email address'],
            ['role', 'user role'],
            ['is_active', 'active status'],
            ['required_services', 'service type'],
            // New field name mappings
            ['assigned_to', 'assigned to'],
            ['avatar_url', 'avatar url'],
            ['case_id', 'case id'],
            ['category_id', 'category'],
            ['client_id', 'client id'],
            ['closed_at', 'closed at'],
            ['contact_number', 'contact number'],
            ['decision', 'decision'],
            ['decision_reason', 'decision reason'],
            ['due_date', 'due date'],
            ['draft_client_data', 'draft client data'],
            ['priority', 'priority'],
            ['suffix', 'suffix'],
        ];
    }

    public static function fieldValueProvider(): array
    {
        return [
            ['case_files', 'status', 'OPEN', 'Open'],
            ['case_files', 'status', 'CLOSED', 'Closed'],
            ['case_files', 'is_active', true, 'Yes'],
            ['case_files', 'is_active', false, 'No'],
            ['case_files', 'is_active', 1, 'Yes'],
            ['case_files', 'is_active', 0, 'No'],
            ['users', 'role', 'CASE_MANAGER', 'Case Manager'],
            ['users', 'role', 'AGENCY', 'Agency Focal'],
            ['users', 'role', 'ADMIN', 'System Admin'],
            ['case_files', 'status', null, 'not set'],
            ['case_files', 'notes', ['foo' => 'bar'], '1 fields'],
            ['clients', 'client_type', 'OFW', 'OFW'],
        ];
    }

    public static function loginTimezoneProvider(): array
    {
        return [
            'UTC to Asia/Manila' => [
                Carbon::parse('2026-06-02 00:29:00', 'UTC'),
                'Asia/Manila',
                'signed in on June 2, 2026 at 8:29 AM',
            ],
        ];
    }
}
