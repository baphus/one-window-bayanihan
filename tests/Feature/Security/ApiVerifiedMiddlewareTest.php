<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiVerifiedMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unverified_user_is_redirected_from_api_routes(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/api/clients');

        // verified middleware redirects to verification.notice for non-JSON web requests
        $response->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function verified_user_can_access_api_routes(): void
    {
        $user = User::factory()->create(); // email_verified_at defaults to now()

        $response = $this->actingAs($user)->get('/api/clients');

        $response->assertOk();
    }

    #[Test]
    public function unverified_user_gets_forbidden_for_json_api_requests(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->get('/api/clients', ['Accept' => 'application/json']);

        // verified middleware returns 403 for JSON/AJAX requests
        $response->assertForbidden();
    }
}
