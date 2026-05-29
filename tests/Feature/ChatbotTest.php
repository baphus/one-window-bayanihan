<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\Ai\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_ai_response_when_configured()
    {
        SystemSetting::setValue('chatbot_enabled', 'true');
        SystemSetting::setValue('chatbot_api_key', 'test-key');

        $this->instance(AiService::class, Mockery::mock(AiService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->andReturn('This is an AI-generated reply.');
        }));

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'What services do you offer?',
        ]);

        $response->assertOk()
            ->assertJson(['reply' => 'This is an AI-generated reply.']);
    }

    public function test_fallback_when_ai_throws()
    {
        SystemSetting::setValue('chatbot_enabled', 'true');
        SystemSetting::setValue('chatbot_api_key', 'test-key');

        $this->instance(AiService::class, Mockery::mock(AiService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->andThrow(new \Exception('API failure'));
        }));

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'hello',
        ]);

        $response->assertOk()
            ->assertSeeText('Hello!');
    }
}
