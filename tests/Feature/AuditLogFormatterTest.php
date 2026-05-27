<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditLogFormatterTest extends TestCase
{
    public function test_it_generates_create_description(): void
    {
        $log = new AuditLog([
            'action' => 'CREATE',
            'module' => 'case_files',
            'new_value' => ['case_number' => 'CAS-001', 'status' => 'OPEN'],
            'user_id' => null,
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $result = $formatter->format($log);

        $this->assertStringContainsString('created', $result);
        $this->assertStringContainsString('Case', $result);
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

        $this->assertStringContainsString('updated', $result);
        $this->assertStringContainsString('from Processing to Completed', $result);
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

        $this->assertStringContainsString('deleted', $result);
        $this->assertStringContainsString('Agency', $result);
    }

    public function test_it_handles_login_action(): void
    {
        $log = new AuditLog([
            'action' => 'LOGIN',
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $result = $formatter->format($log);

        $this->assertStringContainsString('signed in', $result);
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
            'action' => 'VIEW',
            'module' => 'clients',
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $this->assertStringContainsString('System viewed Client', $formatter->format($log));
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

        $this->assertStringContainsString(';', $result);
    }

    public function test_it_handles_view_action(): void
    {
        $log = new AuditLog([
            'action' => 'VIEW',
            'module' => 'clients',
            'timestamp' => now(),
        ]);

        $formatter = new AuditLogFormatter;

        $result = $formatter->format($log);

        $this->assertStringContainsString('viewed Client', $result);
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

    public function test_format_changes_old_null(): void
    {
        $formatter = new AuditLogFormatter;

        $this->assertEquals('set status to Open', $formatter->formatChanges(null, ['status' => 'OPEN']));
    }

    public function test_format_changes_new_null(): void
    {
        $formatter = new AuditLogFormatter;

        $this->assertEquals('cleared status', $formatter->formatChanges(['status' => 'OPEN'], null));
    }

    public function test_it_uses_loaded_user_name_when_available(): void
    {
        $log = new AuditLog([
            'action' => 'VIEW',
            'module' => 'clients',
            'timestamp' => now(),
        ]);

        $log->setRelation('user', new User(['name' => 'Maria Santos']));

        $formatter = new AuditLogFormatter;

        $this->assertStringContainsString('Maria Santos viewed Client', $formatter->format($log));
    }

    public function actionProvider(): array
    {
        return [
            ['CREATE', 'created'],
            ['UPDATE', 'updated'],
            ['DELETE', 'deleted'],
            ['VIEW', 'viewed'],
            ['LOGIN', 'signed in'],
            ['LOGOUT', 'signed out'],
        ];
    }

    public function moduleProvider(): array
    {
        return [
            ['case_files', 'Case'],
            ['clients', 'Client'],
            ['referrals', 'Referral'],
            ['agencies', 'Agency'],
            ['users', 'User'],
            ['helpdesk_articles', 'Helpdesk Article'],
            ['services', 'Service'],
            ['client_addresses', 'Address'],
            ['client_employments', 'Employment Record'],
            ['milestones', 'Milestone'],
            ['referral_attachments', 'Attachment'],
        ];
    }

    public function fieldNameProvider(): array
    {
        return [
            ['status', 'status'],
            ['case_number', 'case number'],
            ['first_name', 'first name'],
            ['email', 'email address'],
            ['role', 'user role'],
            ['is_active', 'active status'],
            ['required_services', 'service type'],
        ];
    }

    public function fieldValueProvider(): array
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
            ['case_files', 'notes', ['foo' => 'bar'], '{"foo":"bar"}'],
            ['clients', 'client_type', 'OFW', 'Overseas Filipino Worker'],
        ];
    }
}
