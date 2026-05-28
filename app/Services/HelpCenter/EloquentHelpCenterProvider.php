<?php

namespace App\Services\HelpCenter;

use App\Contracts\HelpCenterProviderInterface;
use App\Models\HelpdeskArticle;
use App\Services\HelpdeskService;
use Illuminate\Support\Collection;

class EloquentHelpCenterProvider implements HelpCenterProviderInterface
{
    public function __construct(
        private readonly HelpdeskService $helpdeskService,
    ) {}

    public function search(string $query, array $filters = []): Collection
    {
        return HelpdeskArticle::published()
            ->where('visibility', 'public')
            ->with(['category', 'tags'])
            ->where(function ($q) use ($query) {
                $term = '%'.$query.'%';

                $q->where('title', 'ILIKE', $term)
                    ->orWhere('excerpt', 'ILIKE', $term)
                    ->orWhere('content_markdown', 'ILIKE', $term);
            })
            ->orderByRaw('CASE WHEN title ILIKE ? THEN 0 WHEN excerpt ILIKE ? THEN 1 ELSE 2 END', [$query.'%', $query.'%'])
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get();
    }

    public function getById(string $id): ?HelpdeskArticle
    {
        return HelpdeskArticle::published()
            ->where('visibility', 'public')
            ->with(['category', 'tags'])
            ->find($id);
    }

    public function getBySlug(string $slug): ?HelpdeskArticle
    {
        return HelpdeskArticle::published()
            ->where('visibility', 'public')
            ->with(['category', 'tags'])
            ->where('slug', $slug)
            ->first();
    }
}
