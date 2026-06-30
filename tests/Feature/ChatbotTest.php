<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_empty_message_returns_422()
    {
        $response = $this->postJson(route('chatbot.message'), [
            'message' => '',
        ]);

        $response->assertUnprocessable();
    }

    public function test_long_message_returns_422()
    {
        $response = $this->postJson(route('chatbot.message'), [
            'message' => str_repeat('a', 1001),
        ]);

        $response->assertUnprocessable();
    }

    public function test_keyword_hello_response()
    {
        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'hello',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['reply'])
            ->assertSeeText('Hello!');
    }

    public function test_ai_failure_returns_503(): void
    {
        config()->set('ai-chatbot.enabled', 'true');

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'What services do you offer?',
        ]);

        $response->assertStatus(503)
            ->assertJson(['reply' => null, 'error' => 'AI service is currently unavailable. Please try again later.']);
    }

    public function test_guide_file_exists(): void
    {
        $this->assertFileExists(resource_path('guides/ofw-case-tracking.md'));
    }
}
