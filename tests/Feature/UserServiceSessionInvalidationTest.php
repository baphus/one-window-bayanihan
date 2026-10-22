<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The guarantee `UserService::invalidateSessions()` makes is not the same on
 * every session driver, and the only thing stopping that from being mistaken
 * for a full session kill is that the gap is stated and reported. These cover
 * the driver-dependent half of the contract.
 */
class UserServiceSessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private function sessionRowFor(User $user): string
    {
        $id = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        return $id;
    }

    public function test_database_driver_deletes_sessions_and_rotates_remember_token_without_warning(): void
    {
        config(['session.driver' => 'database']);

        $logger = $this->createMock(Logger::class);
        $logger->method('withContext')->willReturn($logger);
        $logger->expects($this->never())->method('warning');
        Log::swap($logger);

        $user = User::factory()->create();
        $sessionId = $this->sessionRowFor($user);

        app(UserService::class)->invalidateSessions($user);

        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_non_database_driver_still_rotates_remember_token_and_warns_that_sessions_survive(): void
    {
        config(['session.driver' => 'redis']);

        $user = User::factory()->create();
        $originalRememberToken = $user->remember_token;

        $logger = $this->createMock(Logger::class);
        $logger->method('withContext')->willReturn($logger);
        $logger->expects($this->once())->method('warning')
            ->with(
                $this->callback(fn ($message) => str_contains($message, 'non-database session driver')),
                $this->callback(fn ($context) => $context['session_driver'] === 'redis'
                    && $context['user_id'] === $user->id),
            );
        Log::swap($logger);

        app(UserService::class)->invalidateSessions($user);

        // The remember-token rotation is the one half that holds on every
        // driver, so a redis deployment still kills every remembered device.
        $this->assertNotSame($originalRememberToken, $user->fresh()->remember_token);
    }
}
