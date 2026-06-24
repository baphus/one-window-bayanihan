<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\Contracts\ToolEnabledAiProvider;
use App\Services\Ai\ToolDefinitions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicToolProvider extends AnthropicProvider implements ToolEnabledAiProvider
{
    public function getTools(): array
    {
        return ToolDefinitions::forAnthropic();
    }

    public function sendMessageWithTools(
        string $message,
        array $tools,
        callable $toolHandler,
        array $context = []
    ): string {
        try {
            $payload = [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'tools' => $tools,
                'messages' => [
                    ['role' => 'user', 'content' => $message],
                ],
            ];

            if ($this->systemPrompt !== '') {
                $payload['system'] = $this->systemPrompt;
            }

            // First request: send message with tool definitions
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', $payload);

            if ($response->failed()) {
                Log::warning('Anthropic tool API error', [
                    'status' => $response->status(),
                ]);

                return '';
            }

            $body = $response->json();
            $stopReason = $body['stop_reason'] ?? '';

            // If no tool use, return direct response
            if ($stopReason !== 'tool_use') {
                return $body['content'][0]['text'] ?? '';
            }

            // Build message history with tool results
            $messages = [
                ['role' => 'user', 'content' => $message],
            ];

            $assistantContent = [];
            $toolResults = [];

            foreach ($body['content'] as $block) {
                if ($block['type'] === 'text') {
                    $assistantContent[] = [
                        'type' => 'text',
                        'text' => $block['text'] ?? '',
                    ];
                } elseif ($block['type'] === 'tool_use') {
                    $assistantContent[] = [
                        'type' => 'tool_use',
                        'id' => $block['id'],
                        'name' => $block['name'],
                        'input' => $block['input'],
                    ];

                    // Execute the tool handler
                    $args = $block['input'] ?? [];
                    $result = call_user_func($toolHandler, $block['name'], $args);

                    $toolResults[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content' => is_string($result) ? $result : json_encode($result),
                    ];
                }
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $assistantContent,
            ];

            $messages[] = [
                'role' => 'user',
                'content' => $toolResults,
            ];

            // Send follow-up with tool results
            $followUpPayload = [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'messages' => $messages,
            ];

            if ($this->systemPrompt !== '') {
                $followUpPayload['system'] = $this->systemPrompt;
            }

            $followUp = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', $followUpPayload);

            if ($followUp->failed()) {
                Log::warning('Anthropic tool follow-up failed', [
                    'status' => $followUp->status(),
                ]);

                return '';
            }

            $result = $followUp->json();

            return $result['content'][0]['text'] ?? '';

        } catch (\Throwable $e) {
            Log::warning('Anthropic tool calling failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);

            return '';
        }
    }
}
