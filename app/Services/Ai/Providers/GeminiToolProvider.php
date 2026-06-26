<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\ToolEnabledAiProvider;
use App\Services\Ai\GeminiProvider;
use App\Services\Ai\ToolDefinitions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiToolProvider extends GeminiProvider implements ToolEnabledAiProvider
{
    public function getTools(): array
    {
        return ToolDefinitions::forGemini();
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
