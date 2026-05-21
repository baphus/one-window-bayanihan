<?php

namespace App\Services;

use App\Models\HelpdeskArticle;
use App\Models\HelpdeskArticleFeedback;
use App\Models\HelpdeskArticleRevision;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class HelpdeskService
{
    public function getPublishedArticles(array $filters = [])
    {
        $query = HelpdeskArticle::published()
            ->with(['category', 'tags', 'author'])
            ->orderBy('created_at', 'desc');

        $query = $this->applySearchFilter($query, $filters);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['tag_id'])) {
            $query->whereHas('tags', fn ($q) => $q->where('helpdesk_tags.id', $filters['tag_id']));
        }

        return $query->paginate(12);
    }

    public function getArticleBySlug(string $slug): ?HelpdeskArticle
    {
        $query = HelpdeskArticle::published()
            ->with(['category', 'tags', 'author', 'feedback'])
            ->where('slug', $slug);

        return $query->first();
    }

    public function getSubcategories(string $categoryId)
    {
        return HelpdeskCategory::where('parent_id', $categoryId)
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->withCount('publishedArticles')
            ->orderBy('sort_order')
            ->get();
    }

    public function getCategories()
    {
        return HelpdeskCategory::where('is_active', true)
            ->where('is_deleted', false)
            ->withCount('publishedArticles')
            ->orderBy('sort_order')
            ->get();
    }

    public function getCategoryTree()
    {
        $parents = HelpdeskCategory::where('is_active', true)
            ->where('is_deleted', false)
            ->whereNull('parent_id')
            ->with(['children' => function ($q) {
                $q->where('is_active', true)->where('is_deleted', false)->orderBy('sort_order')
                    ->withCount('publishedArticles');
            }])
            ->withCount('publishedArticles')
            ->orderBy('sort_order')
            ->get();

        // Sum direct + child article counts for each parent category
        return $parents->map(function ($cat) {
            $cat->total_articles = $cat->published_articles_count
                + $cat->children->sum('published_articles_count');

            return $cat;
        });
    }

    public function getTags()
    {
        return HelpdeskTag::where('is_deleted', false)
            ->withCount('articles')
            ->orderBy('name')
            ->get();
    }

    public function getFeaturedArticles(int $limit = 5)
    {
        $query = HelpdeskArticle::published()->featured()
            ->with(['category', 'tags'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit);

        return $query->get();
    }

    public function getRecentArticles(int $limit = 5)
    {
        $query = HelpdeskArticle::published()
            ->with(['category', 'author'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit);

        return $query->get();
    }

    public function getPopularArticles(int $limit = 5)
    {
        $query = HelpdeskArticle::published()
            ->with(['category', 'author'])
            ->withCount(['feedback as helpful_count' => fn ($q) => $q->where('helpful', true)])
            ->orderBy('helpful_count', 'desc')
            ->limit($limit);

        return $query->get();
    }

    public function searchArticles(string $query, array $filters = [])
    {
        $q = HelpdeskArticle::published()
            ->with(['category', 'tags', 'author'])
            ->where(function (Builder $b) use ($query) {
                $term = '%'.$query.'%';
                $b->where('title', 'ILIKE', $term)
                    ->orWhere('excerpt', 'ILIKE', $term)
                    ->orWhere('content_markdown', 'ILIKE', $term);
            })
            ->orderByRaw('CASE WHEN title ILIKE ? THEN 0 WHEN excerpt ILIKE ? THEN 1 ELSE 2 END', [$query.'%', $query.'%'])
            ->orderBy('updated_at', 'desc');

        if (! empty($filters['category_id'])) {
            $q->where('category_id', $filters['category_id']);
        }

        return $q->paginate(12);
    }

    public function getRelatedArticles(string $articleId, int $limit = 3)
    {
        $article = HelpdeskArticle::find($articleId);
        if (! $article) {
            return collect();
        }

        $tagIds = $article->tags->pluck('id')->toArray();

        $query = HelpdeskArticle::published()
            ->where('id', '!=', $articleId)
            ->with(['category', 'tags'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit);

        if (! empty($tagIds)) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('helpdesk_tags.id', $tagIds));
        } elseif ($article->category_id) {
            $query->where('category_id', $article->category_id);
        }

        return $query->get();
    }

    public function createArticle(array $data, string $userId): HelpdeskArticle
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        $data['author_id'] = $userId;

        if (($data['status'] ?? 'draft') === 'published') {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        $article = HelpdeskArticle::create($data);

        if (! empty($tagIds)) {
            $article->tags()->sync($tagIds);
        }

        $this->createRevision($article, $userId, 'Initial version');

        return $article->load(['category', 'tags', 'author']);
    }

    public function updateArticle(string $id, array $data, string $userId): HelpdeskArticle
    {
        $article = HelpdeskArticle::findOrFail($id);

        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        if (isset($data['status']) && $data['status'] === 'published' && ! $article->published_at) {
            $data['published_at'] = now();
        }

        $revisionData = array_intersect_key($article->toArray(), array_flip(['title', 'content_markdown', 'excerpt']));
        $revisionNotes = $data['edit_notes'] ?? 'Updated article';
        unset($data['edit_notes']);

        $article->update($data);

        $this->createRevision($article, $userId, $revisionNotes, $revisionData);

        if ($tagIds !== null) {
            $article->tags()->sync($tagIds);
        }

        return $article->fresh(['category', 'tags', 'author']);
    }

    public function deleteArticle(string $id): void
    {
        $article = HelpdeskArticle::findOrFail($id);
        $article->delete();
    }

    public function restoreArticle(string $id): void
    {
        $article = HelpdeskArticle::withTrashed()->findOrFail($id);
        $article->restore();
    }

    public function toggleFeatured(string $id): HelpdeskArticle
    {
        $article = HelpdeskArticle::findOrFail($id);
        $article->update(['featured' => ! $article->featured]);

        return $article->fresh();
    }

    public function submitFeedback(array $data): HelpdeskArticleFeedback
    {
        return HelpdeskArticleFeedback::create($data);
    }

    public function getArticlesForAdmin(array $filters = [])
    {
        $query = HelpdeskArticle::with(['category', 'tags', 'author'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'ILIKE', $term)
                    ->orWhere('excerpt', 'ILIKE', $term);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['featured'])) {
            $query->where('featured', true);
        }

        if (! empty($filters['trashed'])) {
            $query->where('is_deleted', true);
        }

        return $query->paginate(15);
    }

    public function getCategoryById(string $id): HelpdeskCategory
    {
        return HelpdeskCategory::with(['parent', 'children'])->findOrFail($id);
    }

    public function getAllCategoriesForAdmin()
    {
        return HelpdeskCategory::with(['parent', 'children'])
            ->withCount('articles')
            ->orderBy('sort_order')
            ->get();
    }

    public function createCategory(array $data): HelpdeskCategory
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return HelpdeskCategory::create($data);
    }

    public function updateCategory(string $id, array $data): HelpdeskCategory
    {
        $category = HelpdeskCategory::findOrFail($id);
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $category->update($data);

        return $category->fresh();
    }

    public function deleteCategory(string $id): void
    {
        $category = HelpdeskCategory::findOrFail($id);
        $category->update(['is_active' => false, 'is_deleted' => true]);
        $category->delete();
    }

    public function createTag(array $data): HelpdeskTag
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return HelpdeskTag::create($data);
    }

    public function updateTag(string $id, array $data): HelpdeskTag
    {
        $tag = HelpdeskTag::findOrFail($id);
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $tag->update($data);

        return $tag->fresh();
    }

    public function deleteTag(string $id): void
    {
        $tag = HelpdeskTag::findOrFail($id);
        $tag->delete();
    }

    public function getRevisions(string $articleId)
    {
        return HelpdeskArticleRevision::where('article_id', $articleId)
            ->with('editor')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function createRevision(HelpdeskArticle $article, string $userId, string $notes, ?array $overrideData = null): void
    {
        $data = $overrideData ?? $article->toArray();

        HelpdeskArticleRevision::create([
            'article_id' => $article->id,
            'title' => $data['title'] ?? $article->title,
            'content_markdown' => $data['content_markdown'] ?? $article->content_markdown,
            'excerpt' => $data['excerpt'] ?? $article->excerpt,
            'edited_by' => $userId,
            'edit_notes' => $notes,
        ]);
    }

    private function applySearchFilter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'ILIKE', $term)
                    ->orWhere('excerpt', 'ILIKE', $term);
            });
        }

        return $query;
    }
}
