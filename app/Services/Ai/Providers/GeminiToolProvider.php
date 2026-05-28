<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\ToolEnabledAiProvider;
use App\Services\Ai\GeminiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiToolProvider extends GeminiProvider implements ToolEnabledAiProvider
{
    public function getTools(): array
    {
        return [
            [
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
            [
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
            [
                'name' => 'searchAgencies',
                'description' => 'Search for agencies (government offices) by name or description. E.g., OWWA, DMW, TESDA, DSWD, DOLE, POEA.',
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
            [
                'name' => 'getAgencyServices',
                'description' => 'Get services offered by a specific agency. Use this to list what services an agency provides.',
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
            [
                'name' => 'getServiceRequirements',
                'description' => 'Get the document requirements for a specific service. Use this to answer what documents an OFW needs to prepare.',
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
            [
                'name' => 'searchServices',
                'description' => 'Search for services by name or description across all agencies. E.g., repatriation assistance, legal aid, skills training.',
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
            [
                'name' => 'getCaseStatuses',
                'description' => 'Get the list of case statuses and their meanings. Use this to explain what each case status means (New, In Progress, Pending, Completed, Closed).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
            [
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
            [
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
            [
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
            [
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
            [
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
        ];
    }

    /**
     * Convert internal tool definitions to Gemini function declarations.
     */
    private function buildGeminiTools(array $tools): array
    {
        $declarations = [];
        foreach ($tools as $tool) {
            $declaration = [
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
            ];

            if (isset($tool['parameters'])) {
                $declaration['parameters'] = $tool['parameters'];
            }

            $declarations[] = $declaration;
        }

        return [['functionDeclarations' => $declarations]];
    }

    /**
     * Parse a Gemini response part and handle function calls.
     */
    private function parsePart(array $part, array &$toolResults, callable $toolHandler): string
    {
        if (isset($part['text'])) {
            return $part['text'];
        }

        if (isset($part['functionCall'])) {
            $fc = $part['functionCall'];
            $name = $fc['name'];
            $args = $fc['args'] ?? [];
            $result = call_user_func($toolHandler, $name, $args);

            $toolResults[] = [
                'functionResponse' => [
                    'name' => $name,
                    'response' => [
                        'name' => $name,
                        'content' => is_string($result) ? $result : json_encode($result),
                    ],
                ],
            ];
        }

        return '';
    }

    /**
     * Build the Gemini request payload.
     */
    private function buildGeminiPayload(string $message, array $tools, array $context): array
    {
        $contents = [];

        if ($this->systemPrompt !== '') {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $this->systemPrompt]],
            ];
            $contents[] = [
                'role' => 'model',
                'parts' => [['text' => 'Understood. I will follow those instructions and use the available tools to help the user.']],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message]],
        ];

        $payload = [
            'contents' => $contents,
            'tools' => $this->buildGeminiTools($tools),
            'generationConfig' => [
                'temperature' => $this->temperature,
                'maxOutputTokens' => $this->maxTokens,
            ],
        ];

        if (! empty($context['system_prompt'])) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $context['system_prompt']]],
            ];
            // Remove the manual system prompt injection since we use systemInstruction
            array_shift($payload['contents']);
            array_shift($payload['contents']);
        }

        return $payload;
    }

    public function sendMessageWithTools(
        string $message,
        array $tools,
        callable $toolHandler,
        array $context = []
    ): string {
        try {
            $payload = $this->buildGeminiPayload($message, $tools, $context);

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::withHeaders([
                'content-type' => 'application/json',
            ])->post($url, $payload);

            if ($response->failed()) {
                Log::warning('Gemini tool API error', [
                    'status' => $response->status(),
                ]);

                return '';
            }

            $body = $response->json();
            $candidate = $body['candidates'][0] ?? [];
            $parts = $candidate['content']['parts'] ?? [];

            // Check if any part is a function call
            $hasFunctionCall = false;
            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $hasFunctionCall = true;
                    break;
                }
            }

            if (! $hasFunctionCall) {
                $text = '';
                foreach ($parts as $part) {
                    if (isset($part['text'])) {
                        $text .= $part['text'];
                    }
                }

                return $text;
            }

            // Process function calls
            $toolResults = [];
            $assistantText = '';

            foreach ($parts as $part) {
                $assistantText .= $this->parsePart($part, $toolResults, $toolHandler);
            }

            if (empty($toolResults)) {
                return $assistantText;
            }

            // Build follow-up request with the function response
            $followUpPayload = $payload;
            $followUpPayload['contents'][] = [
                'role' => 'model',
                'parts' => $parts,
            ];
            $followUpPayload['contents'][] = [
                'role' => 'function',
                'parts' => $toolResults,
            ];

            $followUp = Http::withHeaders([
                'content-type' => 'application/json',
            ])->post($url, $followUpPayload);

            if ($followUp->failed()) {
                Log::warning('Gemini tool follow-up failed', [
                    'status' => $followUp->status(),
                ]);

                return '';
            }

            $resultBody = $followUp->json();
            $resultCandidate = $resultBody['candidates'][0] ?? [];
            $resultParts = $resultCandidate['content']['parts'] ?? [];

            $finalText = '';
            foreach ($resultParts as $part) {
                if (isset($part['text'])) {
                    $finalText .= $part['text'];
                }
            }

            return $finalText;
        } catch (\Throwable $e) {
            Log::warning('Gemini tool calling failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);

            return '';
        }
    }
}
