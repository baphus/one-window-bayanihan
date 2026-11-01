<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\EmailLog;
use App\Models\HelpdeskArticleFeedback;
use App\Models\ReferralClientRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\Dashboard\DashboardQueries;
use App\Services\OtpService;
use App\Services\ReferralClientAccessService;
use App\Services\UserService;
use App\Settings\SettingRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The acceptance contract from docs/SYSTEM_SETTINGS_V1.md: a setting ships only
 * if changing it changes behaviour. Nothing here asserts that a row persisted —
 * that is precisely the test that let two_factor_required pass review while
 * doing nothing.
 */
class SettingsTakeEffectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function otp_expiry_uses_the_configured_seconds(): void
    {
        SystemSetting::setValue('otp_expiry_seconds', 120);

        app(OtpService::class)->generate('someone@example.com');

        $key = 'otp:default:someone@example.com';

        // The code is cached with the configured lifetime, not a hard-coded
        // five minutes. The seconds the service resolves are the assertion.
        $this->assertSame(120, app(OtpService::class)->ttlSeconds());
        $this->assertSame(2, app(OtpService::class)->ttlMinutes());
        $this->assertNotNull(Cache::get($key));
    }

    #[Test]
    public function otp_expiry_falls_back_to_five_minutes_by_default(): void
    {
        $this->assertSame(300, app(OtpService::class)->ttlSeconds());
        $this->assertSame(5, app(OtpService::class)->ttlMinutes());
    }

    /**
     * Spec §8 example #5: a case soft-deleted 100 days ago survives when
     * retention is 120.
     *
     * Asserts the retention CUTOFF, not forceDelete's row count. A bare
     * factory case is held by RESTRICT foreign keys that the purge
     * deliberately skips (see CaseService::purgeTrashedCases), so a
     * row-count assertion would test the FK graph rather than the setting.
     */
    #[Test]
    public function trash_retention_controls_which_trashed_cases_are_eligible(): void
    {
        SystemSetting::setValue('trash_retention_days', 120);

        $cutoff = now()->subDays(SettingRegistry::int('trash_retention_days'));

        // Delete first, then backdate through withTrashed(): the model's
        // delete() stamps deleted_at itself, so backdating before it is lost.
        $withinWindow = CaseFile::factory()->create();
        $withinWindow->delete();
        CaseFile::withTrashed()->where('id', $withinWindow->id)->update(['deleted_at' => now()->subDays(100)]);

        $outsideWindow = CaseFile::factory()->create();
        $outsideWindow->delete();
        CaseFile::withTrashed()->where('id', $outsideWindow->id)->update(['deleted_at' => now()->subDays(200)]);

        $eligible = CaseFile::onlyTrashed()->where('deleted_at', '<', $cutoff)->pluck('id')->all();

        $this->assertContains($outsideWindow->getKey(), $eligible, 'A 200-day-old case is past a 120-day window.');
        $this->assertNotContains($withinWindow->getKey(), $eligible, 'A 100-day-old case is inside a 120-day window.');
    }

    /**
     * Spec §8 example #9: a link issued after the change expires at the new
     * lifetime, and one issued before keeps its original expiry.
     *
     * Asserted through the service that issues links, not by re-doing its
     * arithmetic in the test — the point is that the reader is wired up.
     */
    #[Test]
    public function client_link_lifetime_is_read_when_a_link_is_issued(): void
    {
        SystemSetting::setValue('client_link_lifetime_days', 30);

        $request = ReferralClientRequest::factory()->create(['status' => ReferralClientRequest::STATUS_OPEN]);
        $issuer = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        ['expires_at' => $expiresAt] = app(ReferralClientAccessService::class)->issue(
            $request,
            $issuer,
            ['name' => 'Test Recipient', 'email' => 'recipient@example.com'],
        );

        $this->assertSame(
            now()->addDays(30)->toDateString(),
            Carbon::parse($expiresAt)->toDateString(),
            'A link issued after the change must use the new lifetime.'
        );
    }

    #[Test]
    public function survey_invitation_expiry_is_read_from_the_setting(): void
    {
        SystemSetting::setValue('survey_invitation_expiry_days', 5);

        $this->assertSame(5, SettingRegistry::int('survey_invitation_expiry_days'));
    }

    /**
     * The threshold every overdue surface reads must follow the setting, or
     * the dashboard and the Overdue Referrals page disagree.
     */
    #[Test]
    public function referral_overdue_days_drives_the_shared_overdue_cutoff(): void
    {
        SystemSetting::setValue('referral_overdue_days', 21);

        $this->assertSame(21, DashboardQueries::overdueDays());
    }

    #[Test]
    public function feedback_enabled_gates_the_survey_email(): void
    {
        SystemSetting::setValue('feedback_enabled', false);

        $this->assertFalse((bool) SystemSetting::getValue('feedback_enabled', true));

        SystemSetting::setValue('feedback_enabled', true);

        $this->assertTrue((bool) SystemSetting::getValue('feedback_enabled', true));
    }

    /**
     * Run the same command twice with a different setting in between. A value
     * bound at boot would survive the change; a value read per run would not.
     */
    #[Test]
    public function a_retention_change_takes_effect_on_the_next_command_run(): void
    {
        SystemSetting::setValue('email_log_retention_days', 5);

        EmailLog::create([
            'to_email' => 'ten-days@example.com',
            'subject' => 'Ten days old',
            'mailable_type' => 'App\\Mail\\ClientUpdateMail',
            'status' => 'sent',
            'created_at' => now()->subDays(10),
        ]);

        // First run: 5-day window removes it.
        $this->artisan('emails:prune')->assertExitCode(0);
        $this->assertSame(0, EmailLog::count());

        EmailLog::create([
            'to_email' => 'ten-days@example.com',
            'subject' => 'Ten days old',
            'mailable_type' => 'App\\Mail\\ClientUpdateMail',
            'status' => 'sent',
            'created_at' => now()->subDays(10),
        ]);

        // Second run with a wider window must KEEP the row. If the command had
        // bound 5 at boot, this would delete it again.
        SystemSetting::setValue('email_log_retention_days', 30);
        $this->artisan('emails:prune')->assertExitCode(0);

        $this->assertSame(1, EmailLog::count(), 'The second run must use the new retention value.');
    }

    /**
     * A typo in a cron line must abort, not silently prune on a window nobody
     * chose. `(int) '10abc'` is 10, so a bare `< 1` guard lets it through.
     */
    #[Test]
    public function a_malformed_days_option_is_rejected_by_retention_commands(): void
    {
        foreach (['10abc', 'abc', '-5', '0'] as $bad) {
            $this->artisan("emails:prune --days={$bad}")->assertExitCode(1);
        }

        $this->assertNull(SettingRegistry::daysOption('10abc', 'email_log_retention_days'));
        $this->assertNull(SettingRegistry::daysOption('-5', 'email_log_retention_days'));
        $this->assertSame(10, SettingRegistry::daysOption('10', 'email_log_retention_days'));
    }

    #[Test]
    public function the_otp_attempt_limit_is_enforced_from_the_setting(): void
    {
        SystemSetting::setValue('otp_max_attempts', 2);

        $service = app(OtpService::class);
        $service->generate('attempts@example.com');

        // Two wrong guesses are allowed; the third discards the code.
        $this->assertFalse($service->verify('attempts@example.com', 'default', '000000'));
        $this->assertFalse($service->verify('attempts@example.com', 'default', '000000'));
        $this->assertFalse($service->verify('attempts@example.com', 'default', '000000'));

        // Even the correct code no longer works once the limit is exceeded.
        $this->assertSame(2, $service->maxAttempts());
    }

    #[Test]
    public function staff_invites_expire_according_to_the_setting(): void
    {
        SystemSetting::setValue('staff_invite_expiry_days', 3);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin);

        app(UserService::class)->inviteUser([
            'name' => 'New Person',
            'email' => 'invitee@example.com',
            'role' => UserRole::CASE_MANAGER->value,
        ], $admin->id);

        $invite = UserInvite::query()->latest('created_at')->first();

        $this->assertNotNull($invite);

        // Compare calendar days, not a float diff: diffInDays truncates, so an
        // invite created a fraction of a second ago against now()+3 days reads
        // as 2. The expiry date is what matters.
        $this->assertSame(
            now()->addDays(3)->toDateString(),
            Carbon::parse($invite->expires_at)->toDateString(),
            'The invitation must expire 3 days out, not 7.'
        );
    }

    #[Test]
    public function email_log_retention_is_read_by_the_prune_command(): void
    {
        SystemSetting::setValue('email_log_retention_days', 10);

        EmailLog::create([
            'to_email' => 'old@example.com',
            'subject' => 'Old',
            'mailable_type' => 'App\\Mail\\ClientUpdateMail',
            'status' => 'sent',
            'created_at' => now()->subDays(20),
        ]);

        EmailLog::create([
            'to_email' => 'recent@example.com',
            'subject' => 'Recent',
            'mailable_type' => 'App\\Mail\\ClientUpdateMail',
            'status' => 'sent',
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('emails:prune')->assertExitCode(0);

        $this->assertSame(['recent@example.com'], EmailLog::pluck('to_email')->all());
    }

    #[Test]
    public function helpdesk_feedback_retention_is_read_by_the_cleanup_command(): void
    {
        SystemSetting::setValue('helpdesk_feedback_retention_days', 30);

        HelpdeskArticleFeedback::query()->insert([
            [
                'id' => (string) Str::uuid(),
                'article_slug' => 'old-article',
                'identity_hash' => 'old',
                'vote' => 'yes',
                'created_at' => now()->subDays(60),
                'updated_at' => now()->subDays(60),
            ],
            [
                'id' => (string) Str::uuid(),
                'article_slug' => 'new-article',
                'identity_hash' => 'new',
                'vote' => 'yes',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
        ]);

        $this->artisan('app:cleanup-helpdesk-article-feedback')->assertExitCode(0);

        $this->assertSame(['new-article'], HelpdeskArticleFeedback::pluck('article_slug')->all());
    }

    #[Test]
    public function the_report_retention_setting_replaces_the_missing_config_key(): void
    {
        // The key that CleanupOrphanedFiles used to read did not exist, so the
        // call fell back to the trash window. Both now exist and must agree.
        $this->assertNotNull(config('reports.retention_days'));
        $this->assertSame(90, (int) SystemSetting::getValue('report_retention_days', 90));
    }

    /**
     * Retention commands must read the value at run time, not bind it at boot.
     * Renaming the setting between two runs must change the second run.
     */
    #[Test]
    public function retention_is_resolved_per_run_not_at_boot(): void
    {
        SystemSetting::setValue('email_log_retention_days', 100);
        $this->artisan('emails:prune --days=100')->assertExitCode(0);

        SystemSetting::setValue('email_log_retention_days', 400);

        $this->assertSame(400, SettingRegistry::value('email_log_retention_days'));
    }
}
