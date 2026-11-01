<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Controllers\Api\ReadinessController;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Monitoring pages must be admin-only, and the health page must actually
 * reflect the state it claims to report.
 */
class MonitoringPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_page_is_admin_only(): void
    {
        foreach ([UserRole::CASE_MANAGER, UserRole::AGENCY, UserRole::OFW] as $role) {
            $user = User::factory()->create(['role' => $role->value]);

            $this->actingAs($user)
                ->get(route('admin.system-health.index'))
                ->assertForbidden();
        }
    }

    public function test_health_page_reports_a_fresh_scheduler_as_ok(): void
    {
        Cache::put(ReadinessController::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String());

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($admin)->get(route('admin.system-health.index'));

        $response->assertOk();

        $scheduler = collect($response->viewData('page')['props']['checks'])
            ->firstWhere('key', 'scheduler');

        $this->assertSame('ok', $scheduler['status']);
    }

    /**
     * The whole point of the page: a stopped scheduler must be visible. This
     * asserts the behaviour, not that a row was written.
     */
    public function test_health_page_reports_a_stale_scheduler_as_failing(): void
    {
        $staleAfter = (int) config('monitoring.thresholds.scheduler_stale_seconds');
        Cache::put(
            ReadinessController::SCHEDULER_HEARTBEAT_KEY,
            now()->subSeconds($staleAfter + 60)->toIso8601String()
        );

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($admin)->get(route('admin.system-health.index'));

        $scheduler = collect($response->viewData('page')['props']['checks'])
            ->firstWhere('key', 'scheduler');

        $this->assertSame('fail', $scheduler['status']);
        $this->assertFalse($response->viewData('page')['props']['healthy']);
    }

    public function test_email_log_page_is_admin_only(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAs($user)->get(route('admin.email-logs.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.security-log.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.failed-jobs.index'))->assertForbidden();
    }

    public function test_email_log_page_filters_by_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        EmailLog::create([
            'to_email' => 'delivered@example.com',
            'subject' => 'Your case update',
            'mailable_type' => 'App\\Mail\\ClientUpdateMail',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        EmailLog::create([
            'to_email' => 'bounced@example.com',
            'subject' => 'Your case update',
            'mailable_type' => 'App\\Mail\\ClientUpdateMail',
            'status' => 'failed',
            'error_message' => 'resend: 550 mailbox unavailable',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.email-logs.index', ['status' => 'failed']));

        $response->assertOk();

        $rows = collect($response->viewData('page')['props']['logs']['data']);

        $this->assertCount(1, $rows);
        $this->assertSame('bounced@example.com', $rows->first()['to_email']);
    }

    /**
     * A failed email must be findable by recipient — the question an admin
     * actually has.
     */
    public function test_email_log_search_matches_recipient_and_subject(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        foreach (['maria@example.com', 'jose@example.com'] as $email) {
            EmailLog::create([
                'to_email' => $email,
                'subject' => 'Referral received',
                'mailable_type' => 'App\\Mail\\ReferralOverdueMail',
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.email-logs.index', ['search' => 'maria']));

        $rows = collect($response->viewData('page')['props']['logs']['data']);

        $this->assertCount(1, $rows);
        $this->assertSame('maria@example.com', $rows->first()['to_email']);
    }

    /**
     * There is no `security` module, so the page must still find AUTH and MFA
     * rows — the failure mode being guarded against is shipping an empty table.
     */
    public function test_security_log_shows_auth_and_mfa_events_but_not_unrelated_rows(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $insert = function (string $module, string $action, ?string $category = null) use ($admin) {
            DB::table('audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'action' => $action,
                'module' => $module,
                'description' => "{$module} {$action}",
                'user_id' => $admin->id,
                'timestamp' => now(),
                'chain_seq' => DB::table('audit_logs')->max('chain_seq') + 1,
                'category' => $category,
            ]);
        };

        $insert('auth', 'LOGIN');
        $insert('mfa', 'LOGIN_FAILED');
        $insert('user', 'ADMIN_RESET_PASSWORD', 'security');
        $insert('case', 'UPDATE');

        $response = $this->actingAs($admin)->get(route('admin.security-log.index'));

        $response->assertOk();

        $rows = collect($response->viewData('page')['props']['logs']['data']);

        $this->assertCount(3, $rows);
        $this->assertFalse($rows->contains(fn ($row) => $row['module'] === 'case'));
    }
}
