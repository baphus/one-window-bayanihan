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
            [
                'type' => 'function',
                'function' => [
                    'name' => 'searchAgencies',
                    'description' => 'Search for agencies (government offices) by name or description. E.g., OWWA, DMW, TESDA, DSWD, DOLE. Returns agency ID, name, short name, description, and contact info.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Search keywords for agency name or description',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maximum results (1-10)',
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
                    'name' => 'getAgencyServices',
                    'description' => 'Get services offered by a specific agency. Use this to list what services an agency provides to OFWs and clients.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'agencyId' => [
                                'type' => 'string',
                                'description' => 'The UUID of the agency',
                            ],
                        ],
                        'required' => ['agencyId'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'getServiceRequirements',
                    'description' => 'Get the document requirements for a specific service. Use this to answer what documents an OFW needs to prepare for a service.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'serviceId' => [
                                'type' => 'string',
                                'description' => 'The UUID of the service',
                            ],
                        ],
                        'required' => ['serviceId'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'searchServices',
                    'description' => 'Search for services by name or description across all agencies. E.g., repatriation assistance, legal aid, skills training, social welfare.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Search keywords for service name or description',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maximum results (1-10)',
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
                    'name' => 'getCaseStatuses',
                    'description' => 'Get the list of case statuses and their meanings. Use this to explain what each case status means (New, In Progress, Pending, Completed, Closed).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'searchCases',
                    'description' => '[REQUIRES LOGIN] Search for cases by case number, tracker number, or client name. Only available for logged-in staff (case managers, agency focal, admin). Returns matching case summaries.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Search keywords (case number, tracker number, client name)',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maximum results (1-10)',
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
                    'name' => 'getCaseDetail',
                    'description' => '[REQUIRES LOGIN] Get detailed information about a specific case including client info, referrals, and timeline.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'caseId' => [
                                'type' => 'string',
                                'description' => 'The UUID of the case',
                            ],
                        ],
                        'required' => ['caseId'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'initiateCaseOTP',
                    'description' => 'Send a 6-digit OTP verification code to the email address registered with a case. Use this when an OFW wants to verify their identity to access their case details. The user must provide their tracker number first.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'trackerNumber' => [
                                'type' => 'string',
                                'description' => 'The tracker number of the case to verify access for',
                            ],
                        ],
                        'required' => ['trackerNumber'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'verifyCaseOTP',
                    'description' => 'Verify a 6-digit OTP code that was sent to the OFW\'s email. After successful verification, case details will be accessible in the session.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'trackerNumber' => [
                                'type' => 'string',
                                'description' => 'The tracker number that was used to initiate the OTP',
                            ],
                            'otp' => [
                                'type' => 'string',
                                'description' => 'The 6-digit verification code sent to the email',
                            ],
                        ],
                        'required' => ['trackerNumber', 'otp'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'getVerifiedCaseInfo',
                    'description' => 'Get case details for a tracker number that has been previously verified via OTP in the current session.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'trackerNumber' => [
                                'type' => 'string',
                                'description' => 'The verified tracker number',
                            ],
                        ],
                        'required' => ['trackerNumber'],
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
