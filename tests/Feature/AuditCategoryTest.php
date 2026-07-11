<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_events_are_stamped_security(): void
    {
        $entry = AuditLog::create([
            'action' => 'LOGIN_FAILED',
            'module' => 'auth',
            'description' => 'Failed sign-in attempt for x@example.com',
            'timestamp' => now(),
        ]);

        $this->assertSame('security', $entry->category);
    }

    public function test_business_entity_change_by_a_user_is_stamped_data(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->actingAs($user);

        $entry = AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'referral',
            'user_id' => $user->id,
            'old_value' => ['status' => 'PENDING'],
            'new_value' => ['status' => 'PROCESSING'],
            'timestamp' => now(),
        ]);

        $this->assertSame('data', $entry->category);
    }

    public function test_configuration_change_by_a_user_is_stamped_admin(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $entry = AuditLog::create([
            'action' => 'CREATE',
            'module' => 'case_category',
            'user_id' => $admin->id,
            'new_value' => ['name' => 'Repatriation'],
            'timestamp' => now(),
        ]);

        $this->assertSame('admin', $entry->category);
    }

    public function test_unattributed_console_write_is_stamped_system(): void
    {
        // Observer-created entry from a factory in the test process (console,
        // no authenticated user) — automated activity.
        $category = CaseCategory::factory()->create();

        $entry = AuditLog::where('module', 'case_category')
            ->where('entity_id', $category->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('system', $entry->category);
    }

    public function test_newly_observed_models_produce_audit_entries(): void
    {
        $category = CaseCategory::factory()->create(['name' => 'Legal Aid']);

        $entry = AuditLog::where('module', 'case_category')
            ->where('entity_id', $category->id)
            ->where('action', 'CREATE')
            ->first();

        $this->assertNotNull($entry);
        $this->assertNotEmpty($entry->description);
    }

    public function test_backfill_leaves_no_null_categories(): void
    {
        $user = User::factory()->create();

        AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'clients',
            'user_id' => $user->id,
            'timestamp' => now(),
        ]);
        AuditLog::create([
            'action' => 'LOGIN',
            'module' => 'auth',
            'user_id' => $user->id,
            'timestamp' => now(),
        ]);

        // Simulate pre-category rows.
        DB::statement("SET app.allow_audit_mutations = 'true'");
        try {
            DB::table('audit_logs')->update(['category' => null]);
        } finally {
            DB::statement("SET app.allow_audit_mutations = ''");
        }

        $this->artisan('audit:backfill-categories')->assertExitCode(0);

        $this->assertSame(0, AuditLog::whereNull('category')->count());
        $this->assertSame('security', AuditLog::where('action', 'LOGIN')->first()->category);
        $this->assertSame('data', AuditLog::where('module', 'clients')->first()->category);
    }
}
