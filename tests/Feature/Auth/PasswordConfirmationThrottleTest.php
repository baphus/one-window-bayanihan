<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\SetPostgresSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordConfirmationThrottleTest extends TestCase
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
     * Every password-confirmation endpoint re-checks the account password, so a
     * hijacked session must not be able to guess it for free. The named
     * `password-confirm` limiter allows five attempts a minute; the sixth is 429.
     */
    #[Test]
    public function it_rate_limits_password_confirmation_attempts_to_five_per_minute(): void
    {
        $user = User::factory()->mfaEnabled()->create([
            'password' => Hash::make('real-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)
                ->postJson(route('profile.mfa.disable'), [
                    'password' => 'wrong-password-'.$i,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        }

        $this->actingAs($user)
            ->postJson(route('profile.mfa.disable'), [
                'password' => 'wrong-password-5',
            ])
            ->assertStatus(429);
    }

    /**
     * The limiter keys on the authenticated user id, so exhausting one account's
     * budget must not lock out everyone else.
     */
    #[Test]
    public function it_keeps_separate_buckets_per_user(): void
    {
        $lockedOut = User::factory()->mfaEnabled()->create(['password' => Hash::make('real-password')]);
        $other = User::factory()->mfaEnabled()->create(['password' => Hash::make('real-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($lockedOut)
                ->postJson(route('profile.mfa.disable'), ['password' => 'wrong-password-'.$i])
                ->assertStatus(422);
        }

        $this->actingAs($lockedOut)
            ->postJson(route('profile.mfa.disable'), ['password' => 'wrong-password'])
            ->assertStatus(429);

        $this->actingAs($other)
            ->postJson(route('profile.mfa.disable'), ['password' => 'wrong-password'])
            ->assertStatus(422);
    }

    /**
     * Guards the fix itself: every password-confirmation endpoint must stay
     * on the named limiter, not just the one exercised above.
     */
    #[Test]
    public function it_applies_the_password_confirm_limiter_to_every_guarded_route(): void
    {
        $routes = [
            'profile.mfa.disable',
            'profile.mfa.recovery-codes.regenerate',
            'admin.users.reset-mfa',
            'admin.users.email-change.send-otp',
            'admin.users.email-change.verify-otp',
            'password.update',
        ];

        foreach ($routes as $name) {
            $middleware = Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [];

            $this->assertContains('throttle:password-confirm', $middleware, "Route [{$name}] is missing the password-confirm throttle.");
        }

        // POST /confirm-password is the framework default and carries no name,
        // so it has to be matched on the URI + method pair instead.
        $confirmPassword = collect(Route::getRoutes()->getRoutes())->first(
            fn ($route) => $route->uri() === 'confirm-password'
                && in_array('POST', $route->methods(), true)
        );

        $this->assertNotNull($confirmPassword, 'POST /confirm-password route is missing.');
        $this->assertContains(
            'throttle:password-confirm',
            $confirmPassword->gatherMiddleware(),
            'POST /confirm-password is missing the password-confirm throttle.'
        );
    }

    /**
     * The account password update route re-checks current_password on every
     * request, so it is a password oracle like the confirmation endpoints.
     */
    #[Test]
    public function it_rate_limits_password_update_attempts_to_five_per_minute(): void
    {
        $user = User::factory()->create(['password' => Hash::make('real-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)
                ->putJson(route('password.update'), [
                    'current_password' => 'wrong-password-'.$i,
                    'password' => 'New-P@ssw0rd1',
                    'password_confirmation' => 'New-P@ssw0rd1',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['current_password']);
        }

        $this->actingAs($user)
            ->putJson(route('password.update'), [
                'current_password' => 'wrong-password-5',
                'password' => 'New-P@ssw0rd1',
                'password_confirmation' => 'New-P@ssw0rd1',
            ])
            ->assertStatus(429);
    }
}
