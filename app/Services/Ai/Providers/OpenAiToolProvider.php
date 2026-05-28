<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\ToolEnabledAiProvider;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Support\Facades\Log;
use OpenAI;

class OpenAiToolProvider extends OpenAiProvider implements ToolEnabledAiProvider
{
    public function getTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'searchHelpCenter',
                    'description' => 'Search the Help Center for articles matching a query. Use this to find documentation relevant to the user\'s question.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'The search query (natural language or keywords)',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maximum number of results to return (1-10)',
                                'default' => 5,
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'getArticleBySlug',
                    'description' => 'Get a specific Help Center article by its URL slug. Use this when you need the full content of a particular article.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'slug' => [
                                'type' => 'string',
                                'description' => 'The article URL slug (e.g., "how-to-reset-password")',
                            ],
                        ],
                        'required' => ['slug'],
                    ],
                ],
            ],
        ];
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
                return $choice->message->content ?? '';
            }

            $toolCalls = $choice->message->toolCalls ?? [];
            if (empty($toolCalls)) {
                return $choice->message->content ?? '';
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

            return $followUp->choices[0]->message->content ?? '';

        } catch (\Throwable $e) {
            Log::warning('OpenAI tool calling failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);

            return '';
        }
    }
}
