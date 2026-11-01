<?php

namespace App\Services;

use App\Models\HelpdeskArticleFeedback;
use App\Settings\SettingRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HelpdeskFeedbackService
{
    public function identity(Request $request, bool $initialize = false): ?string
    {
        if ($request->user()) {
            return hash('sha256', 'user:'.$request->user()->getAuthIdentifier());
        }

        $identity = $request->cookie('helpdesk_voter');
        if (! is_string($identity) || ! Str::isUuid($identity)) {
            if (! $initialize) {
                return null;
            }
            $identity = (string) Str::uuid();
            cookie()->queue(cookie('helpdesk_voter', $identity, 525600, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return hash('sha256', 'guest:'.$identity);
    }

    public function currentVote(string $slug, string $identity): ?string
    {
        return HelpdeskArticleFeedback::query()->where('article_slug', $slug)
            ->where('identity_hash', $identity)->where('updated_at', '>=', $this->retentionCutoff())
            ->value('vote');
    }

    public function store(string $slug, string $identity, string $vote): void
    {
        HelpdeskArticleFeedback::upsert([[
            'id' => (string) Str::uuid(),
            'article_slug' => $slug,
            'identity_hash' => $identity,
            'vote' => $vote,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['article_slug', 'identity_hash'], ['vote', 'updated_at']);
    }

    private function retentionCutoff(): Carbon
    {
        // Must match CleanupHelpdeskArticleFeedback, which deletes on the same
        // window. These disagreed by living as two separate hard-coded 12s;
        // both now read helpdesk_feedback_retention_days.
        $days = SettingRegistry::int('helpdesk_feedback_retention_days');

        return now()->subDays($days);
    }

    public function summary(HelpdeskArticleService $articles): array
    {
        $counts = HelpdeskArticleFeedback::query()
            ->where('updated_at', '>=', $this->retentionCutoff())
            ->selectRaw("article_slug, COUNT(*) FILTER (WHERE vote = 'yes') AS yes, COUNT(*) FILTER (WHERE vote = 'no') AS no")
            ->groupBy('article_slug')->get()->keyBy('article_slug');

        return collect($articles->all())->map(function (array $article) use ($counts): array {
            $count = $counts->get($article['slug']);
            $yes = (int) ($count->yes ?? 0);
            $no = (int) ($count->no ?? 0);
            $total = $yes + $no;

            return ['slug' => $article['slug'], 'title' => $article['title'], 'yes' => $yes, 'no' => $no,
                'total' => $total, 'percent' => $total ? (int) round($yes * 100 / $total) : null];
        })->sort(fn (array $a, array $b) => ($b['no'] <=> $a['no']) ?: strcmp($a['title'], $b['title']))->values()->all();
    }
}
