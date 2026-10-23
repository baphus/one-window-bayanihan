<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\SetPostgresSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The `otp` limiter is shared by guest registration and the authenticated
 * email-change flow. It keyed on the pending-registration email alone, so a
 * signed-in user — who has no pending registration — always produced
 * `guest|<ip>`: every signed-in user behind one office router shared a single
 * five-per-minute bucket. Authenticated callers now key on their own user id,
 * with the pending email still used for guests.
 */
class OtpThrottleIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(SetPostgresSession::class);
    }

    #[Test]
    public function it_rate_limits_email_change_otp_attempts_to_five_per_minute(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)
                ->from(route('profile.edit'))
                ->post(route('profile.email-change.send-otp'), [
                    'password' => 'wrong-password-'.$i,
                    'new_email' => 'new-'.$i.'@example.test',
                ])
                ->assertStatus(302);
        }

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.email-change.send-otp'), [
                'password' => 'wrong-password-5',
                'new_email' => 'new-5@example.test',
            ])
            ->assertStatus(429);
    }

    /**
     * The defect the fix exists for: with a session-only key, one user's five
     * attempts locked out a second user behind the same address.
     */
    #[Test]
    public function exhausting_one_users_otp_budget_does_not_lock_out_another_behind_the_same_ip(): void
    {
        $lockedOut = User::factory()->create(['password' => Hash::make('password')]);
        $other = User::factory()->create(['password' => Hash::make('password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($lockedOut)
                ->from(route('profile.edit'))
                ->post(route('profile.email-change.send-otp'), [
                    'password' => 'wrong-password-'.$i,
                    'new_email' => 'new-'.$i.'@example.test',
                ])
                ->assertStatus(302);
        }

        $this->actingAs($lockedOut)
            ->from(route('profile.edit'))
            ->post(route('profile.email-change.send-otp'), [
                'password' => 'wrong-password-5',
                'new_email' => 'new-5@example.test',
            ])
            ->assertStatus(429);

        $this->actingAs($other)
            ->from(route('profile.edit'))
            ->post(route('profile.email-change.send-otp'), [
                'password' => 'wrong-password',
                'new_email' => 'other-new@example.test',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('password');
    }

    /**
     * The guest half of the limiter must keep keying on the pending registration
     * email, so a filer still gets five code attempts per address.
     */
    #[Test]
    public function it_rate_limits_registration_otp_attempts_per_pending_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postAsPendingFiler('filer@example.test')
                ->post(route('register.profile.store'), [])
                ->assertStatus(302);
        }

        $this->postAsPendingFiler('filer@example.test')
            ->post(route('register.profile.store'), [])
            ->assertStatus(429);
    }

    /**
     * ...and the key really is that email: a different filer is a different
     * bucket, not a victim of the first one's exhausted one.
     */
    #[Test]
    public function a_second_pending_filer_is_not_locked_out_by_the_first(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postAsPendingFiler('filer@example.test')
                ->post(route('register.profile.store'), [])
                ->assertStatus(302);
        }

        $this->postAsPendingFiler('other-filer@example.test')
            ->post(route('register.profile.store'), [])
            ->assertStatus(302);
    }

    /**
     * A guest attempt and a signed-in attempt with the same address must not
     * share a bucket — that collision is the reason the identity is now the user
     * id when there is one.
     */
    #[Test]
    public function a_registration_attempt_does_not_spend_an_authenticated_users_budget(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->postAsPendingFiler($user->email)
            ->post(route('register.profile.store'), [])
            ->assertStatus(302);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.email-change.send-otp'), [
                'password' => 'wrong-password',
                'new_email' => 'new@example.test',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('password');
    }

    private function postAsPendingFiler(string $email): static
    {
        return $this->withSession([
            'pending_registration' => [
                'credentials' => [
                    'email' => $email,
                    'name' => 'Pending Filer',
                    'password' => Hash::make('password'),
                ],
            ],
        ]);
    }
}
