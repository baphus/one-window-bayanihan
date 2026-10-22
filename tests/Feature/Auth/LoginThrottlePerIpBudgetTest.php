<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\SetPostgresSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The login limiter keyed solely on the submitted email — and the email field is
 * attacker-controlled and always present, so the `?: $request->ip()` fallback
 * behind it could never fire. One host could take a fresh password guess at an
 * invented address every ten requests and walk the whole directory, while the
 * per-email+ip pair limit in LoginRequest::throttleKey() never noticed, because
 * every guess used a different address. The limiter now carries a second,
 * IP-only key alongside the per-account one.
 */
class LoginThrottlePerIpBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // SetPostgresSession uses parameterized SET SESSION which PostgreSQL does not
        // support via PDO prepared statements ($1 placeholder). Skip it here; we are
        // testing throttle behaviour, not RLS context propagation.
        $this->withoutMiddleware(SetPostgresSession::class);
    }

    /**
     * Thirty guesses spread across thirty invented addresses must exhaust the IP's
     * budget: none of those share a per-account key with each other, so only the
     * per-IP limit can catch the spray.
     */
    #[Test]
    public function it_caps_password_spraying_across_invented_addresses_from_one_ip(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post(route('login'), [
                'email' => 'sprayed-'.$i.'@example.test',
                'password' => 'wrong-password-'.$i,
            ])->assertStatus(302);
        }

        $this->post(route('login'), [
            'email' => 'sprayed-30@example.test',
            'password' => 'wrong-password-30',
        ])->assertStatus(429);
    }

    /**
     * A real user who fumbles their own password must still get in — the per-IP
     * limit is a spray cap, not a lockout, and it is set above the per-account
     * limit precisely so one mistyped login never eats a colleague's budget.
     */
    #[Test]
    public function it_still_admits_a_correct_password_before_the_ip_budget_runs_out(): void
    {
        $user = User::factory()->create(['password' => Hash::make('P@ssw0rd!')]);

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password-'.$i,
            ])->assertStatus(302);
        }

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'P@ssw0rd!',
        ])->assertStatus(302);

        $this->assertAuthenticatedAs($user);
    }
}
