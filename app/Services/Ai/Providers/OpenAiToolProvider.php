<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\ToolEnabledAiProvider;
use App\Services\Ai\OpenAiProvider;
use App\Services\Ai\ToolDefinitions;
use Illuminate\Support\Facades\Log;
use OpenAI;

class OpenAiToolProvider extends OpenAiProvider implements ToolEnabledAiProvider
{
    public function getTools(): array
    {
        return ToolDefinitions::forOpenAI();
    }

    public function sendMessageWithTools(
        string $message,
        array $tools,
        callable $toolHandler,
        array $context = []
    ): string {
        try {
            $messages = [];

            $systemPrompt = $context['system_prompt'] ?? $this->systemPrompt;
            if ($systemPrompt !== '') {
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            }

            $messages[] = ['role' => 'user', 'content' => $message];

            $client = OpenAI::client($this->apiKey);

            // First request: send message with tool definitions
            $response = $client->chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

            $choice = $response->choices[0];
            $finishReason = $choice->finishReason ?? '';

            // If no tool calls, return direct response
            if ($finishReason !== 'tool_calls') {
                $content = $choice->message->content ?? '';

                return $content;
            }

            $toolCalls = $choice->message->toolCalls ?? [];
            if (empty($toolCalls)) {
                $content = $choice->message->content ?? '';

                return $content;
            }

            // Add assistant message with tool_calls
            $assistantMessage = [
                'role' => 'assistant',
                'content' => $choice->message->content ?? null,
                'tool_calls' => [],
            ];

            foreach ($toolCalls as $tc) {
                $assistantMessage['tool_calls'][] = [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $tc->function->name,
                        'arguments' => $tc->function->arguments,
                    ],
                ];
            }
            $messages[] = $assistantMessage;

            // Process each tool call and append results
            foreach ($toolCalls as $tc) {
                $args = json_decode($tc->function->arguments, true) ?? [];
                $result = call_user_func($toolHandler, $tc->function->name, $args);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tc->id,
                    'content' => is_string($result) ? $result : json_encode($result),
                ];
            }

            // Send follow-up with tool results
            $followUp = $client->chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

            $finalContent = $followUp->choices[0]->message->content ?? '';

            return $finalContent;

        } catch (\Throwable $e) {
            Log::warning('OpenAI tool calling failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);

            return '';
        }
    }
}
