<?php

namespace Tests\Feature;

use App\Services\Ai\AiService;
use App\Services\Ai\Providers\AnthropicToolProvider;
use App\Services\Ai\Providers\GeminiToolProvider;
use App\Services\Ai\Providers\OpenAiToolProvider;
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
        config()->set('ai-chatbot.enabled', 'true');
        config()->set('ai-chatbot.api_key', 'test-key');

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
        config()->set('ai-chatbot.enabled', 'true');
        config()->set('ai-chatbot.api_key', 'test-key');

        $this->instance(AiService::class, Mockery::mock(AiService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->andThrow(new \Exception('API failure'));
        }));

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'hello',
        ]);

        $response->assertStatus(503)
            ->assertJson(['reply' => null, 'error' => 'AI service is currently unavailable. Please try again later.']);
    }

    public function test_openai_tool_provider_returns_4_tools(): void
    {
        $provider = new OpenAiToolProvider('test-key', 'gpt-4o-mini', '', 0.7, 500);
        $tools = $provider->getTools();

        $this->assertCount(4, $tools);

        $first = $tools[0];
        $this->assertArrayHasKey('type', $first);
        $this->assertSame('function', $first['type']);
        $this->assertArrayHasKey('function', $first);
        $this->assertArrayHasKey('name', $first['function']);
    }

    public function test_anthropic_tool_provider_returns_4_tools(): void
    {
        $provider = new AnthropicToolProvider('test-key', 'claude-3-haiku-20240307', '', 0.7, 500);
        $tools = $provider->getTools();

        $this->assertCount(4, $tools);

        $first = $tools[0];
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('input_schema', $first);
    }

    public function test_gemini_tool_provider_returns_4_tools(): void
    {
        $provider = new GeminiToolProvider('test-key', 'gemini-2.0-flash', '', 0.7, 500);
        $tools = $provider->getTools();

        $this->assertArrayHasKey('functionDeclarations', $tools);
        $this->assertCount(4, $tools['functionDeclarations']);

        $first = $tools['functionDeclarations'][0];
        $this->assertArrayHasKey('name', $first);
    }
}
