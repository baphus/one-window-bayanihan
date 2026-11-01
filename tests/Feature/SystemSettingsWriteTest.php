<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Settings\SettingRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SystemSettingsWriteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HandleInertiaRequests::class);

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    #[Test]
    public function clearing_an_integer_setting_keeps_the_stored_value(): void
    {
        SystemSetting::setValue('referral_overdue_days', 30, 'referral', 'Seeded description');

        foreach ([null, ''] as $emptyValue) {
            $response = $this->actingAs($this->admin)
                ->post('/admin/system-settings', ['referral_overdue_days' => $emptyValue]);

            $response->assertRedirect();
            $response->assertSessionHasNoErrors();
        }

        $this->assertSame('30', SystemSetting::getValue('referral_overdue_days'));
    }

    /**
     * A cleared field must not become the type's zero value.
     *
     * This is not a formatting concern: a 0-day retention window flips a
     * cleanup cutoff to "now" and would purge everything on the next run.
     */
    #[Test]
    public function clearing_a_setting_never_writes_zero(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/system-settings', ['referral_overdue_days' => null])
            ->assertRedirect();

        $this->assertFalse(
            SystemSetting::query()->where('key', 'referral_overdue_days')->exists(),
            'A cleared field must not create a row.',
        );

        $this->assertGreaterThanOrEqual(1, (int) SystemSetting::getValue('referral_overdue_days', 7));
    }

    #[Test]
    public function a_valid_value_is_stored(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/system-settings', ['referral_overdue_days' => 14]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame('14', SystemSetting::getValue('referral_overdue_days'));
    }

    #[Test]
    public function values_below_the_declared_minimum_are_rejected(): void
    {
        SystemSetting::setValue('referral_overdue_days', 30);

        foreach ([0, -1, '0'] as $rejected) {
            $response = $this->actingAs($this->admin)
                ->post('/admin/system-settings', ['referral_overdue_days' => $rejected]);

            $response->assertSessionHasErrors('referral_overdue_days');
        }

        $this->assertSame('30', SystemSetting::getValue('referral_overdue_days'));
    }

    /**
     * High-impact settings refuse to save without the confirmation flag, even
     * if a client posts directly to the endpoint.
     */
    #[Test]
    public function retention_changes_are_rejected_without_confirmation(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/system-settings', ['trash_retention_days' => 30]);

        $response->assertSessionHasErrors('confirmed');
        $this->assertFalse(SystemSetting::query()->where('key', 'trash_retention_days')->exists());
    }

    #[Test]
    public function retention_changes_are_saved_with_confirmation(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/system-settings', [
                'trash_retention_days' => 30,
                'confirmed' => true,
            ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame('30', SystemSetting::getValue('trash_retention_days'));
    }

    /**
     * Every save writes one audit row carrying old → new, which is what makes a
     * bad settings change recoverable by hand.
     */
    #[Test]
    public function a_change_is_audited_with_old_and_new_values(): void
    {
        SystemSetting::setValue('otp_expiry_seconds', 300);

        $this->actingAs($this->admin)
            ->post('/admin/system-settings', ['otp_expiry_seconds' => 600])
            ->assertRedirect();

        $entry = AuditLog::where('module', 'system_setting')
            ->where('action', 'UPDATE')
            ->latest('chain_seq')
            ->first();

        $this->assertNotNull($entry, 'Changing a setting must be audited.');
        $this->assertSame($this->admin->id, $entry->user_id);
        $this->assertArrayHasKey('otp_expiry_seconds', (array) $entry->new_value);
        $this->assertArrayHasKey('otp_expiry_seconds', (array) $entry->old_value);
    }

    /**
     * A no-op save writes nothing: audit noise hides real changes.
     */
    #[Test]
    public function saving_an_unchanged_value_writes_no_audit_row(): void
    {
        SystemSetting::setValue('opacity_marker', 'x');
        SystemSetting::setValue('referral_overdue_days', 14);

        $this->actingAs($this->admin)
            ->post('/admin/system-settings', ['referral_overdue_days' => 14])
            ->assertRedirect();

        $this->assertSame(
            0,
            AuditLog::where('module', 'system_setting')->where('action', 'UPDATE')->count()
        );
    }

    #[Test]
    public function updating_a_setting_preserves_its_seeded_category_and_description(): void
    {
        SystemSetting::query()->create([
            'key' => 'referral_overdue_days',
            'value' => '10',
            'category' => 'referral',
            'description' => 'Days before an active referral counts as overdue',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/system-settings', ['referral_overdue_days' => 21])
            ->assertRedirect();

        $setting = SystemSetting::query()->findOrFail('referral_overdue_days');

        $this->assertSame('21', $setting->value);
        $this->assertSame('referral', $setting->category);
        $this->assertSame('Days before an active referral counts as overdue', $setting->description);
    }

    /**
     * The page must report the retention window the pruner actually uses.
     *
     * It read config('email_logs.retention_days') — a key that does not exist
     * — so an admin could lower the setting to 30 and the page would keep
     * claiming 90. Exactly the phantom-key bug this project removed from
     * CleanupOrphanedFiles, reintroduced here; the test pins them together.
     */
    #[Test]
    public function email_log_page_reports_the_retention_the_pruner_uses(): void
    {
        SystemSetting::setValue('email_log_retention_days', 30);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($admin)->get(route('admin.email-logs.index'));

        $this->assertSame(
            (int) SettingRegistry::value('email_log_retention_days'),
            $response->viewData('page')['props']['retentionDays'],
        );
        $this->assertSame(30, $response->viewData('page')['props']['retentionDays']);
    }

    #[Test]
    public function chatbot_reindex_returns_graceful_json_when_the_command_throws(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('chatbot:index')
            ->andThrow(new RuntimeException('Helpdesk corpus is missing.'));

        Log::spy();

        $response = $this->actingAs($this->admin)
            ->post('/admin/system-settings/reindex-chatbot');

        $response->assertStatus(500);
        $response->assertExactJson([
            'success' => false,
            'message' => 'Helpdesk cache refresh failed. Please try again.',
        ]);

        Log::shouldHaveReceived('error')->once();
    }

    #[Test]
    public function chatbot_reindex_returns_graceful_json_when_the_command_exits_non_zero(): void
    {
        Artisan::shouldReceive('call')->once()->with('chatbot:index')->andReturn(1);

        $response = $this->actingAs($this->admin)
            ->post('/admin/system-settings/reindex-chatbot');

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Helpdesk cache refresh failed. Check the server logs for details.',
        ]);

        $this->assertNull(SystemSetting::getValue('chatbot_last_reindexed_at'));
    }
}
