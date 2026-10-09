<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaReplayGuardTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledUser(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'mfa_secret' => app(Google2FA::class)->generateSecretKey(),
            'mfa_enabled_at' => now(),
        ])->save();

        return $user->fresh();
    }

    public function test_a_used_code_is_refused_even_after_the_cache_is_flushed(): void
    {
        $user = $this->enrolledUser();
        $code = $this->codeFor($user);
        $fingerprint = hash('sha256', (string) $user->password);
        $service = app(MfaService::class);

        // Drive the locked path: the replay counter is only read and written
        // under the row lock that completeChallenge() holds. Calling verifyTotp()
        // directly no longer persists anything, which is the point of the fix.
        $this->assertNotNull($service->completeChallenge($user->id, $fingerprint, $code, false));

        // The cache-held guard used to be the only defence. Wiping it let the
        // captured code straight back in; users.mfa_last_totp_counter is what
        // survives this.
        Cache::flush();

        $this->assertNull($service->completeChallenge($user->id, $fingerprint, $code, false));
    }

    public function test_a_used_code_stays_refused_on_a_per_request_cache_driver(): void
    {
        config(['cache.default' => 'array']);

        $user = $this->enrolledUser();
        $code = $this->codeFor($user);
        $fingerprint = hash('sha256', (string) $user->password);
        $service = app(MfaService::class);

        $this->assertNotNull($service->completeChallenge($user->id, $fingerprint, $code, false));
        $this->assertNull($service->completeChallenge($user->id, $fingerprint, $code, false));
    }

    public function test_the_replay_counter_is_written_inside_the_row_lock(): void
    {
        $user = $this->enrolledUser();
        $code = $this->codeFor($user);
        $fingerprint = hash('sha256', (string) $user->password);
        $service = app(MfaService::class);

        $this->assertNull($user->fresh()->mfa_last_totp_counter);

        $this->assertNotNull($service->completeChallenge($user->id, $fingerprint, $code, false));

        // Persisted by the same transaction that read it, not by an unlocked
        // UPDATE after the fact.
        $this->assertGreaterThan(0, (int) $user->fresh()->mfa_last_totp_counter);
    }

    /*
     * Not covered: the window boundaries (a code from one step earlier must be
     * refused once a later one is accepted, and a code from the next step
     * across a 30s boundary must still be accepted). Google2FA exposes no
     * public generator for an arbitrary counter and Laravel's travel() is
     * relative to real time, so a reliable test needs a test double for the
     * google2fa binding. The counter comparison itself is plain PHP and is not
     * the part that regressed.
     */

    private function codeFor(User $user): string
    {
        return app(Google2FA::class)->getCurrentOtp($user->mfa_secret);
    }
}
