<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class ChatbotResponse
{
    public function assemble(array $answer, ChatbotTurn $turn): array
    {
        $validated = Validator::make($answer, [
            'status' => 'required|in:answered,clarification,unsupported', 'reply' => 'required|string|max:8000',
            'evidence_ids' => 'present|array|max:20', 'evidence_ids.*' => 'required|string|max:30',
        ])->validate();
        $sources = $turn->sources($validated['evidence_ids']);
        if ($validated['status'] === 'answered' && $sources === []) {
            throw new RuntimeException('chatbot_missing_evidence');
        }
        if ($validated['status'] !== 'answered') {
            // A model cannot smuggle an uncited answer via a non-answer status.
            return $validated['status'] === 'clarification'
                ? $this->plain('Could you clarify which Bayanihan service or helpdesk topic you mean?', 'clarification')
                : $this->plain("I don't have enough information in the available helpdesk content to answer that.", 'unsupported');
        }
        $reply = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/u', '$1', $validated['reply']);
        $reply = trim(strip_tags(preg_replace('~https?://[^\s<>]+~iu', '', $reply)));
        if ($reply === '') {
            throw new RuntimeException('chatbot_empty_answer');
        }
        $top = $sources[0];
        $payload = $this->plain($reply, 'answered');
        $payload['sources'] = $sources;
        $payload['lastContext'] = ['source_type' => $top['source_type'], 'source_label' => $top['slug'], 'article_title' => $top['article_title'] ?? $top['heading']];
        if (collect($sources)->contains(fn ($s) => $s['source_type'] === 'helpdesk' && $s['slug'] === 'using-public-tracking-portal')) {
            $payload['actions'] = [['label' => 'Go to Tracking Portal', 'url' => route('track.index'), 'icon' => 'track']];
        }

        return $payload;
    }

    public function plain(string $reply, string $status = 'unavailable'): array
    {
        return ['reply' => $reply, 'status' => $status, 'sources' => [], 'actions' => [], 'lastContext' => null];
    }
}
