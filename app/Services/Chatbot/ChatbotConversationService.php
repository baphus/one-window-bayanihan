<?php

namespace App\Services\Chatbot;

use App\Ai\Agents\HelpdeskAgent;
use App\Ai\Tools\GetAgencyServices;
use App\Ai\Tools\ReadHelpdeskArticle;
use App\Ai\Tools\SearchHelpdesk;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

class ChatbotConversationService
{
    public function __construct(private readonly ChatbotKnowledge $knowledge, private readonly ChatbotResponse $responses) {}

    public function reply(array $input, ChatbotAudience $audience): array
    {
        if (! config('ai-chatbot.enabled')) {
            return $this->responses->plain('The helpdesk assistant is currently unavailable.');
        }
        $message = trim($input['message']);
        if (preg_match('/^(hi|hello|hey|good morning|good afternoon|who are you)[!.? ]*$/i', $message)) {
            return $this->responses->plain("Hello! I'm **".config('ai-chatbot.assistant_name').'**, your One Window Bayanihan helpdesk assistant. What would you like help with?', 'greeting');
        }
        $turn = new ChatbotTurn;
        $http = Http::getFacadeRoot();
        $failure = null;
        $invalidFields = [];
        try {
            $history = [];
            foreach (array_slice($input['history'] ?? [], -6) as $entry) {
                $history[] = $entry['role'] === 'bot' ? new AssistantMessage($entry['text']) : new Message('user', $entry['text']);
            }
            $hint = $input['lastContext'] ?? null;
            if (($hint['source_type'] ?? null) === 'helpdesk' && isset($this->knowledge->articles($audience)[$hint['source_label'] ?? ''])) {
                $message .= "\n\nPrevious article hint (not evidence): ".$hint['source_label'];
            }
            $agent = new HelpdeskAgent([
                new SearchHelpdesk($this->knowledge, $audience, $turn), new ReadHelpdeskArticle($this->knowledge, $audience, $turn), new GetAgencyServices($turn),
            ], $history);
            // Scope the remaining deadline to all HTTP requests in this synchronous turn.
            $scopedHttp = clone $http;
            $scopedHttp->globalMiddleware(fn ($handler) => function ($request, array $options) use ($handler, $turn) {
                $remaining = $turn->remaining();
                $options['timeout'] = min($options['timeout'] ?? $remaining, $remaining);
                $options['connect_timeout'] = min(5, $remaining);

                return $handler($request, $options);
            });
            Http::swap($scopedHttp);
            $response = $agent->prompt($message, provider: config('ai-chatbot.provider'), model: config('ai-chatbot.model'), timeout: config('ai-chatbot.timeout'));
            $turn->remaining();
            if (! $response instanceof StructuredAgentResponse) {
                throw new RuntimeException('chatbot_invalid_output');
            }

            return $this->responses->assemble($response->toArray(), $turn);
        } catch (Throwable $e) {
            $failure = class_basename($e);
            if ($e instanceof ValidationException) {
                $invalidFields = array_keys($e->errors());
            }

            return $this->responses->plain("Sorry, I'm having trouble answering right now. Please try again or browse the Helpdesk.");
        } finally {
            Http::swap($http);
            Log::info('Chatbot turn finished', [...$turn->metrics(), 'failure_category' => $failure, 'invalid_fields' => $invalidFields]);
        }
    }
}
