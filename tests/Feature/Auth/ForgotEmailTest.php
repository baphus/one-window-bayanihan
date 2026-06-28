<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\SetPostgresSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ForgotEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable SetPostgresSession middleware which uses parameterized SET SESSION
        // statements that PostgreSQL rejects with PDO prepared statements
        $this->withoutMiddleware(SetPostgresSession::class);
    }

    public function test_forgot_email_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-email');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ForgotEmail')
        );
    }

    public function test_forgot_email_screen_is_accessible_only_to_guests(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/forgot-email');

        $response->assertRedirect('/dashboard');
    }
}
