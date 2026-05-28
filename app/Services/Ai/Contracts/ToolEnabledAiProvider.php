<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiProvider;

interface ToolEnabledAiProvider extends AiProvider
{
    /**
     * Get tool/function definitions for the LLM.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTools(): array;

    /**
     * Send a message with tool definitions and handle tool call responses.
     *
     * @param  string  $message  The user message.
     * @param  array  $tools  Tool definitions.
     * @param  callable(string $name, array $args): mixed  $toolHandler  Handler invoked for each tool call.
     * @param  array<string, mixed>  $context  Additional context (system_prompt, temperature, etc.).
     * @return string The final assistant response.
     */
    public function sendMessageWithTools(
        string $message,
        array $tools,
        callable $toolHandler,
        array $context = []
    ): string;
}
