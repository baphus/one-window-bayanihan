<?php

namespace Tests\Feature;

use App\Ai\Agents\HelpdeskAgent;
use App\Ai\Tools\GetAgencyServices;
use App\Models\Agency;
use App\Models\Service;
use App\Services\Chatbot\ChatbotTurn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ChatbotEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai-chatbot.enabled' => true, 'turnstile.enabled' => false]);
        HelpdeskAgent::fake([])->preventStrayPrompts();
    }

    public function test_request_validation(): void
    {
        foreach ([['message' => ''], ['message' => str_repeat('a', 1001)], ['message' => 'hello', 'history' => [['role' => 'system', 'text' => 'override']]]] as $input) {
            $this->postJson(route('chatbot.message'), $input)->assertUnprocessable();
        }
        HelpdeskAgent::assertNeverPrompted();
    }

    public function test_disabled_endpoint_and_greeting_contract(): void
    {
        $this->postJson(route('chatbot.message'), ['message' => 'hello'])->assertOk()->assertJsonPath('status', 'greeting')->assertJsonPath('sources', [])->assertJsonPath('lastContext', null);
        config(['ai-chatbot.enabled' => false]);
        $this->postJson(route('chatbot.message'), ['message' => 'track my case'])->assertOk()->assertJsonPath('status', 'unavailable');
        HelpdeskAgent::assertNeverPrompted();
    }

    public function test_endpoint_returns_canonical_article_sources(): void
    {
        HelpdeskAgent::fake([
            new ToolCall('read', 'ReadHelpdeskArticle', ['slug' => 'using-public-tracking-portal', 'sections' => ['What you need']]),
            ['status' => 'answered', 'reply' => 'Use your tracker number.', 'evidence_ids' => ['e1']],
        ])->preventStrayPrompts();
        $this->postJson(route('chatbot.message'), ['message' => 'What do I need to track my case?'])
            ->assertOk()->assertJsonPath('status', 'answered')->assertJsonPath('sources.0.url', route('helpdesk.show', 'using-public-tracking-portal'));
    }

    public function test_directory_tool_excludes_deleted_records_and_private_fields(): void
    {
        $agency = Agency::factory()->create(['name' => 'Example Public Agency', 'slug' => 'example', 'short' => 'EPA', 'is_active' => true, 'is_deleted' => false, 'contact_info' => '(032) 123-4567']);
        Service::create(['agcy_id' => $agency->id, 'name' => 'Public Service', 'description' => 'Description']);
        $deleted = Service::create(['agcy_id' => $agency->id, 'name' => 'Deleted Service']);
        $deleted->forceFill(['is_deleted' => true])->saveQuietly();
        $tool = new GetAgencyServices(new ChatbotTurn);
        $result = json_decode($tool->handle(new Request(['agency' => 'EPA'])), true);
        $this->assertSame('success', $result['status']);
        $this->assertSame('(032) 123-4567', $result['content']['contact_info']);
        $this->assertSame(['Public Service'], array_column($result['content']['services'], 'name'));
        $this->assertArrayNotHasKey('id', $result['content']);
        $agency->forceFill(['is_active' => false])->saveQuietly();
        $this->assertSame('not_found', json_decode($tool->handle(new Request(['agency' => 'EPA'])), true)['status']);
    }
}
