<?php

namespace Tests\Feature;

use App\Http\Middleware\LogContext;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogContextTest extends TestCase
{
    public function test_middleware_adds_request_id_to_context(): void
    {
        Log::shouldReceive('withContext')
            ->once()
            ->withArgs(fn (array $context): bool => isset($context['request_id']) &&
                isset($context['method']) &&
                isset($context['url']) &&
                isset($context['ip'])
            );

        $this->get('/login');
    }

    public function test_middleware_sets_user_context_when_authenticated(): void
    {
        $user = User::factory()->create([
            'role' => 'CASE_MANAGER',
        ]);

        Log::shouldReceive('withContext')
            ->once()
            ->withArgs(fn (array $context): bool => isset($context['request_id']) &&
                isset($context['user_id']) &&
                $context['user_id'] === $user->id &&
                isset($context['user_role']) &&
                $context['user_role'] === 'CASE_MANAGER'
            );

        $this->actingAs($user)->get('/dashboard');
    }

    public function test_returns_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_get_request_id_returns_string(): void
    {
        $id = LogContext::getRequestId();
        $this->assertIsString($id);
    }

    public function test_get_request_id_returns_uuid_format(): void
    {
        $id = LogContext::getRequestId();

        // UUID v4 format: 8-4-4-4-12 hex digits
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id
        );
    }

    public function test_middleware_class_exists(): void
    {
        $this->assertTrue(class_exists('App\Http\Middleware\LogContext'));
    }

    public function test_middleware_has_handle_method(): void
    {
        $reflection = new \ReflectionClass('App\Http\Middleware\LogContext');
        $this->assertTrue($reflection->hasMethod('handle'));
    }
}
