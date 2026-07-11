<?php

namespace Tests\Feature\TrackingService;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogFormatterTest extends TestCase
{
    use RefreshDatabase;

    private AuditLogFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = app(AuditLogFormatter::class);
    }

    public function test_format_field_value_statuses(): void
    {
        $f = $this->formatter;

        $this->assertSame('For Compliance', $f->formatFieldValue('case_files', 'status', 'FOR_COMPLIANCE'));
        $this->assertSame('Open', $f->formatFieldValue('case_files', 'status', 'OPEN'));
        $this->assertSame('Pending', $f->formatFieldValue('case_files', 'status', 'PENDING'));
        $this->assertSame('Processing', $f->formatFieldValue('case_files', 'status', 'PROCESSING'));
        $this->assertSame('Completed', $f->formatFieldValue('case_files', 'status', 'COMPLETED'));
        $this->assertSame('Rejected', $f->formatFieldValue('case_files', 'status', 'REJECTED'));
        $this->assertSame('Draft', $f->formatFieldValue('case_files', 'status', 'DRAFT'));
        $this->assertSame('Archived', $f->formatFieldValue('case_files', 'status', 'ARCHIVED'));
        $this->assertSame('Closed', $f->formatFieldValue('case_files', 'status', 'CLOSED'));
    }

    public function test_format_field_value_roles(): void
    {
        $f = $this->formatter;

        $this->assertSame('Case Manager', $f->formatFieldValue('users', 'role', 'CASE_MANAGER'));
        $this->assertSame('Agency Focal', $f->formatFieldValue('users', 'role', 'AGENCY'));
        $this->assertSame('System Admin', $f->formatFieldValue('users', 'role', 'ADMIN'));
    }

    public function test_format_field_value_client_type(): void
    {
        $f = $this->formatter;

        $this->assertSame('OFW', $f->formatFieldValue('clients', 'client_type', 'OFW'));
        $this->assertSame('Next of Kin', $f->formatFieldValue('clients', 'client_type', 'NEXT_OF_KIN'));
    }

    public function test_format_field_value_null(): void
    {
        $this->assertSame('not set', $this->formatter->formatFieldValue('case_files', 'status', null));
    }

    public function test_format_field_value_boolean(): void
    {
        $this->assertSame('Yes', $this->formatter->formatFieldValue('case_files', 'is_active', true));
        $this->assertSame('No', $this->formatter->formatFieldValue('case_files', 'is_active', false));
    }

    public function test_format_module(): void
    {
        $f = $this->formatter;

        $this->assertSame('Case', $f->formatModule('case_files'));
        $this->assertSame('Case', $f->formatModule('cases'));
        $this->assertSame('Case', $f->formatModule('case'));

        $this->assertSame('Referral', $f->formatModule('referrals'));
        $this->assertSame('Referral', $f->formatModule('referral'));

        $this->assertSame('Milestone', $f->formatModule('milestones'));
        $this->assertSame('Milestone', $f->formatModule('milestone'));

        $this->assertSame('Agency', $f->formatModule('agencies'));
        $this->assertSame('Agency', $f->formatModule('agency'));

        $this->assertSame('User', $f->formatModule('users'));
        $this->assertSame('User', $f->formatModule('user'));
    }

    public function test_format_action(): void
    {
        $f = $this->formatter;

        $this->assertSame('created', $f->formatAction('CREATE'));
        $this->assertSame('updated', $f->formatAction('UPDATE'));
        $this->assertSame('deleted', $f->formatAction('DELETE'));
        $this->assertSame('signed in', $f->formatAction('LOGIN'));
        $this->assertSame('signed out', $f->formatAction('LOGOUT'));
    }

    public function test_format_field_name(): void
    {
        $this->assertSame('case number', $this->formatter->formatFieldName('case_number'));
        $this->assertSame('email address', $this->formatter->formatFieldName('email'));
    }

    public function test_format_for_display_structure(): void
    {
        $user = User::factory()->create();
        $log = AuditLog::create([
            'entity_id' => '00000000-0000-0000-0000-000000000000',
            'action' => 'UPDATE',
            'module' => 'case_files',
            'old_value' => ['status' => 'OPEN'],
            'new_value' => ['status' => 'CLOSED'],
            'user_id' => $user->id,
            'timestamp' => now(),
        ]);
        $log->setRelation('user', $user);

        $display = $this->formatter->formatForDisplay($log);

        $this->assertArrayHasKey('message', $display);
        $this->assertArrayHasKey('detail', $display);
        $this->assertArrayHasKey('action', $display);
        $this->assertArrayHasKey('module', $display);
        $this->assertArrayHasKey('actor', $display);
        $this->assertArrayHasKey('timestamp', $display);
        $this->assertArrayHasKey('hasChanges', $display);

        // Assert values are human-readable
        $this->assertSame('UPDATE', $display['action']);
        $this->assertSame('Case', $display['module']);
        $this->assertSame($user->name, $display['actor']);
        $this->assertTrue($display['hasChanges']);
    }

    public function test_format_with_description_override(): void
    {
        $log = AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'case_files',
            'description' => 'Test description',
            'user_id' => null,
            'timestamp' => now(),
        ]);

        $result = $this->formatter->format($log);

        $this->assertSame('Test description', $result);
    }

    public function test_format_with_update_action(): void
    {
        $log = AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'case_files',
            'old_value' => ['tracker_number' => 'OLD-123'],
            'new_value' => ['tracker_number' => 'NEW-456'],
            'description' => null,
            'user_id' => null,
            'timestamp' => now(),
        ]);

        $result = $this->formatter->format($log);

        $this->assertStringContainsString('changed', $result);
        $this->assertStringContainsString('tracker number', $result);
    }

    public function test_output_is_human_readable(): void
    {
        $user = User::factory()->create();

        // Multiple AuditLog entries covering different modules and fields
        $logs = [
            AuditLog::create([
                'action' => 'UPDATE',
                'module' => 'case_files',
                'entity_id' => $user->id,
                'old_value' => ['status' => 'OPEN', 'case_number' => 'CAS-001'],
                'new_value' => ['status' => 'CLOSED', 'case_number' => 'CAS-001'],
                'user_id' => $user->id,
                'timestamp' => now(),
            ]),
            AuditLog::create([
                'action' => 'UPDATE',
                'module' => 'users',
                'entity_id' => $user->id,
                'old_value' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@old.com',
                ],
                'new_value' => [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                    'email' => 'jane@new.com',
                ],
                'user_id' => $user->id,
                'timestamp' => now(),
            ]),
        ];

        // Attach user relation for name resolution
        foreach ($logs as $log) {
            $log->setRelation('user', $user);
        }

        // First log: case_files — has status and case_number
        $result = $this->formatter->format($logs[0]);

        // No UUIDs in formatted output
        $this->assertDoesNotMatchRegularExpression(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            $result,
            'Formatted output should not contain UUIDs: '.$result,
        );

        // No raw underscore-separated field names remaining in output
        $this->assertStringNotContainsString('case_number', $result);

        // Human-readable formatted values and field names ARE present
        $this->assertStringContainsString('Closed', $result);
        $this->assertStringContainsString('status', $result);

        // Second log: users — has first_name, last_name, email
        $result = $this->formatter->format($logs[1]);

        $this->assertDoesNotMatchRegularExpression(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            $result,
            'Formatted output should not contain UUIDs: '.$result,
        );

        $this->assertStringNotContainsString('first_name', $result);
        $this->assertStringNotContainsString('last_name', $result);
        // Raw field name is gone; the human label appears instead
        $this->assertStringContainsString('email address', $result);
        $this->assertStringContainsString('first name', $result);
    }
}
