<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityMirrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_change_description_is_not_mirrored(): void
    {
        $log = AuditLog::create([
            'module' => 'user',
            'action' => 'CHANGE_EMAIL',
            'description' => 'Changed email from old@example.com',
            'timestamp' => now(),
        ]);

        $this->assertSame(0, ActivityEvent::where('audit_log_id', $log->id)->count());
        $this->assertSame(0, ActivityEvent::where('description', 'like', '%old@example.com%')->count());
    }

    public function test_visible_audit_log_is_mirrored_with_actor_and_payload(): void
    {
        $actor = User::factory()->create([
            'name' => 'Case Manager Actor',
            'role' => UserRole::CASE_MANAGER->value,
        ]);
        $description = 'Case was closed after final review.';
        $entityId = '00000000-0000-0000-0000-000000000001';

        $log = AuditLog::create([
            'module' => 'case',
            'action' => 'CLOSE',
            'description' => $description,
            'entity_id' => $entityId,
            'user_id' => $actor->id,
            'timestamp' => now(),
        ]);

        $event = ActivityEvent::where('audit_log_id', $log->id)->firstOrFail();

        $this->assertSame($actor->name, $event->actor_name);
        $this->assertSame($actor->role, $event->actor_role);
        $this->assertSame('case', $event->module);
        $this->assertSame('CLOSE', $event->action);
        $this->assertSame($description, $event->description);
        $this->assertSame($entityId, $event->entity_id);
        $this->assertSame($log->id, $event->audit_log_id);
        $this->assertSame(1, ActivityEvent::where('audit_log_id', $log->id)->count());
    }

    public function test_legacy_module_spelling_is_stored_canonically(): void
    {
        $log = AuditLog::create([
            'module' => 'cases',
            'action' => 'CLOSE',
            'description' => 'Legacy case closure',
            'timestamp' => now(),
        ]);

        $this->assertDatabaseHas('activity_events', [
            'audit_log_id' => $log->id,
            'module' => 'case',
            'action' => 'CLOSE',
        ]);
    }

    public function test_unrecognised_module_is_ignored_without_throwing(): void
    {
        $log = AuditLog::create([
            'module' => 'future_module',
            'action' => 'CREATE',
            'description' => 'Unknown module event',
            'timestamp' => now(),
        ]);

        $this->assertSame(0, ActivityEvent::where('audit_log_id', $log->id)->count());
    }
}
