<?php

namespace Tests\Feature;

use App\Http\Middleware\SetPostgresSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The email-change steps render Profile/Edit from a second controller, and
 * that page binds its props once on mount: `useState(mfaEnabled)` in MfaSetup
 * and `useForm({ notifications_config: { ...notificationPrefs } })`. A payload
 * carrying only the step props therefore did not merely look wrong — it showed
 * an MFA-enrolled user as unenrolled and armed the form to write all-off
 * defaults over the stored notification preferences on the next save.
 */
class EmailChangePropsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Non-default values on purpose: an all-true fixture would also be what a
     * dropped prop degrades towards, so the false entries are the assertion.
     */
    private const PREFS = [
        'email_on_case_assigned' => true,
        'email_on_case_status_change' => false,
        'email_on_referral' => true,
        'in_app_notifications' => false,
    ];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // SetPostgresSession uses parameterized SET SESSION statements that
        // PostgreSQL rejects through PDO prepared statements.
        $this->withoutMiddleware(SetPostgresSession::class);

        Mail::fake();

        $this->user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => Hash::make('password'),
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_enabled_at' => now(),
            'notifications_config' => self::PREFS,
        ]);
    }

    public function test_init_renders_profile_edit_with_mfa_and_notification_props(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->get(route('profile.email-change.init'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('mfaEnabled', true)
            ->where('notificationPrefs', self::PREFS)
            ->has('defaultAgency')
            ->has('mustVerifyEmail')
            ->has('status')
            ->where('email_change_step', 'new-email')
        );
    }

    public function test_send_otp_renders_profile_edit_with_mfa_and_notification_props(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->post(route('profile.email-change.send-otp'), [
                'password' => 'password',
                'new_email' => 'new@example.com',
            ]);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('mfaEnabled', true)
            ->where('notificationPrefs', self::PREFS)
            ->has('defaultAgency')
            ->has('mustVerifyEmail')
            ->has('status')
            ->where('email_change_step', 'otp')
            ->where('email_change_hint', 'ne*@example.com')
        );
    }

    public function test_email_change_renders_carry_the_same_props_as_the_profile_page(): void
    {
        $plain = $this->actingAs($this->user)->get(route('profile.edit'));
        $init = $this->actingAs($this->user)->get(route('profile.email-change.init'));

        $plainProps = array_keys($plain->viewData('page')['props']);
        $initProps = array_keys($init->viewData('page')['props']);

        // The email-change render shares the same profile props and may add
        // flow-specific props when they are not already shared.
        $this->assertSame([], array_values(array_diff($plainProps, $initProps)));
        $this->assertEqualsCanonicalizing(
            [],
            array_values(array_diff($initProps, $plainProps))
        );

        foreach (['mustVerifyEmail', 'status', 'mfaEnabled', 'defaultAgency', 'notificationPrefs'] as $prop) {
            $this->assertContains($prop, $plainProps, "profile.edit must carry {$prop}");
            $this->assertContains($prop, $initProps, "the email-change render must carry {$prop}");
        }
    }

    public function test_email_change_render_leaves_stored_preferences_untouched(): void
    {
        $this->actingAs($this->user)
            ->get(route('profile.email-change.init'));

        $this->assertSame(self::PREFS, $this->user->fresh()->notifications_config);
    }
}
