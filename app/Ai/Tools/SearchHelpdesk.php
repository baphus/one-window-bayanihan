<?php

namespace App\Ai\Tools;

use App\Services\Chatbot\ChatbotAudience;
use App\Services\Chatbot\ChatbotKnowledge;
use App\Services\Chatbot\ChatbotTurn;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class SearchHelpdesk implements Tool
{
    public function __construct(private readonly ChatbotKnowledge $knowledge, private readonly ChatbotAudience $audience, private readonly ChatbotTurn $turn) {}

    public function description(): string
    {
        return 'Search authorized helpdesk articles. Reformulate queries into concise keywords if needed. Results are candidates: read the article before answering.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['query' => $schema->string()->required()];
    }

    public function handle(Request $request): string
    {
        $this->turn->call();
        $query = $request->all()['query'] ?? null;

        return json_encode(is_string($query) && mb_strlen($query) <= 500
            ? ['results' => $this->knowledge->search($query, $this->audience)]
            : ['status' => 'invalid_query'], JSON_THROW_ON_ERROR);
    }
}
