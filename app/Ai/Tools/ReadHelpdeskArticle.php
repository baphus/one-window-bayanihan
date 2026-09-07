<?php

namespace App\Ai\Tools;

use App\Services\Chatbot\ChatbotAudience;
use App\Services\Chatbot\ChatbotKnowledge;
use App\Services\Chatbot\ChatbotTurn;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ReadHelpdeskArticle implements Tool
{
    public function __construct(private readonly ChatbotKnowledge $knowledge, private readonly ChatbotAudience $audience, private readonly ChatbotTurn $turn) {}

    public function description(): string
    {
        return 'Read an authorized helpdesk article by its search-result slug. Supply exact section headings, or an empty sections array for the article. Cite returned evidence_id only when it supports your answer. Omitted sections were NOT read.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['slug' => $schema->string()->required(), 'sections' => $schema->array()->items($schema->string())->required()];
    }

    public function handle(Request $request): string
    {
        $this->turn->call();
        $args = $request->all();
        $slug = $args['slug'] ?? null;
        $sections = $args['sections'] ?? [];
        if (! is_string($slug) || strlen($slug) > 150 || ! is_array($sections) || count($sections) > 20 || array_filter($sections, fn ($s) => ! is_string($s) || strlen($s) > 250)) {
            return '{"status":"invalid_arguments"}';
        }

        return json_encode($this->knowledge->read($slug, $sections, $this->audience, $this->turn), JSON_THROW_ON_ERROR);
    }
}
