<?php

namespace App\Services\Ai;

/**
 * Non-instantiable static tool definitions for AI providers.
 *
 * Returns tool definitions in OpenAI, Anthropic, and Gemini formats.
 * Tools: searchHelpCenter, getArticleBySlug, initiateCaseOTP, verifyCaseOTP,
 *        getVerifiedCaseInfo, getCaseStatuses.
 */
class ToolDefinitions
{
    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct() {}

    /**
     * Get tool definitions in OpenAI format.
     *
     * @return array<int, array{type: string, function: array}>
     */
    public static function forOpenAI(): array
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
                            'category' => [
                                'type' => 'string',
                                'description' => 'Optional category to narrow the search (e.g., "repatriation", "legal", "welfare")',
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
                    'name' => 'initiateCaseOTP',
                    'description' => 'Send a 6-digit OTP verification code to the mobile number registered with a case. Use this when an OFW wants to verify their identity to access their case details.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'mobile' => [
                                'type' => 'string',
                                'description' => 'The mobile number registered with the case to verify access for',
                            ],
                        ],
                        'required' => ['mobile'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'verifyCaseOTP',
                    'description' => 'Verify a 6-digit OTP code that was sent to the OFW\'s mobile number. After successful verification, a case token will be returned for accessing case details.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'mobile' => [
                                'type' => 'string',
                                'description' => 'The mobile number that was used to initiate the OTP',
                            ],
                            'otp' => [
                                'type' => 'string',
                                'description' => 'The 6-digit verification code sent to the mobile number',
                            ],
                        ],
                        'required' => ['mobile', 'otp'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'getVerifiedCaseInfo',
                    'description' => 'Get case details using a verified case token obtained after OTP verification.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'token' => [
                                'type' => 'string',
                                'description' => 'The case token obtained after successful OTP verification',
                            ],
                        ],
                        'required' => ['token'],
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
        ];
    }

    /**
     * Get tool definitions in Anthropic format.
     *
     * @return array<int, array{name: string, description: string, input_schema: array}>
     */
    public static function forAnthropic(): array
    {
        return [
            [
                'name' => 'searchHelpCenter',
                'description' => 'Search the Help Center for articles matching a query. Use this to find documentation relevant to the user\'s question.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query (natural language or keywords)',
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Optional category to narrow the search (e.g., "repatriation", "legal", "welfare")',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'getArticleBySlug',
                'description' => 'Get a specific Help Center article by its URL slug. Use this when you need the full content of a particular article.',
                'input_schema' => [
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
                'name' => 'initiateCaseOTP',
                'description' => 'Send a 6-digit OTP verification code to the mobile number registered with a case. Use this when an OFW wants to verify their identity to access their case details.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'mobile' => [
                            'type' => 'string',
                            'description' => 'The mobile number registered with the case to verify access for',
                        ],
                    ],
                    'required' => ['mobile'],
                ],
            ],
            [
                'name' => 'verifyCaseOTP',
                'description' => 'Verify a 6-digit OTP code that was sent to the OFW\'s mobile number. After successful verification, a case token will be returned for accessing case details.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'mobile' => [
                            'type' => 'string',
                            'description' => 'The mobile number that was used to initiate the OTP',
                        ],
                        'otp' => [
                            'type' => 'string',
                            'description' => 'The 6-digit verification code sent to the mobile number',
                        ],
                    ],
                    'required' => ['mobile', 'otp'],
                ],
            ],
            [
                'name' => 'getVerifiedCaseInfo',
                'description' => 'Get case details using a verified case token obtained after OTP verification.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'token' => [
                            'type' => 'string',
                            'description' => 'The case token obtained after successful OTP verification',
                        ],
                    ],
                    'required' => ['token'],
                ],
            ],
            [
                'name' => 'getCaseStatuses',
                'description' => 'Get the list of case statuses and their meanings. Use this to explain what each case status means (New, In Progress, Pending, Completed, Closed).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
        ];
    }

    /**
     * Get tool definitions in Gemini format.
     *
     * @return array<int, array{functionDeclarations: array}>
     */
    public static function forGemini(): array
    {
        return [
            'functionDeclarations' => [
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
                            'category' => [
                                'type' => 'string',
                                'description' => 'Optional category to narrow the search (e.g., "repatriation", "legal", "welfare")',
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
                    'name' => 'initiateCaseOTP',
                    'description' => 'Send a 6-digit OTP verification code to the mobile number registered with a case. Use this when an OFW wants to verify their identity to access their case details.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'mobile' => [
                                'type' => 'string',
                                'description' => 'The mobile number registered with the case to verify access for',
                            ],
                        ],
                        'required' => ['mobile'],
                    ],
                ],
                [
                    'name' => 'verifyCaseOTP',
                    'description' => 'Verify a 6-digit OTP code that was sent to the OFW\'s mobile number. After successful verification, a case token will be returned for accessing case details.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'mobile' => [
                                'type' => 'string',
                                'description' => 'The mobile number that was used to initiate the OTP',
                            ],
                            'otp' => [
                                'type' => 'string',
                                'description' => 'The 6-digit verification code sent to the mobile number',
                            ],
                        ],
                        'required' => ['mobile', 'otp'],
                    ],
                ],
                [
                    'name' => 'getVerifiedCaseInfo',
                    'description' => 'Get case details using a verified case token obtained after OTP verification.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'token' => [
                                'type' => 'string',
                                'description' => 'The case token obtained after successful OTP verification',
                            ],
                        ],
                        'required' => ['token'],
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
            ],
        ];
    }
}
