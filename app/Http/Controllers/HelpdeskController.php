<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleFeedbackRequest;
use App\Models\HelpdeskCategory;
use App\Services\HelpdeskService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HelpdeskController extends Controller
{
    public function __construct(
        private readonly HelpdeskService $helpdeskService,
    ) {}

    public function index(Request $request)
    {
        $category = $request->get('category');

        if ($category) {
            $slug = $category;
            $cat = HelpdeskCategory::where('slug', $slug)->first();

            if (! $cat) {
                abort(404);
            }

            $subcategories = $this->helpdeskService->getSubcategories($cat->id);
            $hasSubcategories = $subcategories->isNotEmpty();

            return Inertia::render('Helpdesk/Category', [
                'category' => $cat,
                'subcategories' => $hasSubcategories ? $subcategories : null,
                'articles' => $hasSubcategories ? null : $this->helpdeskService->getPublishedArticles(['category_id' => $cat->id]),
                'categories' => $this->helpdeskService->getCategoryTree(),
                'categoryPath' => $this->buildCategoryPath($cat),
            ]);
        }

        return Inertia::render('Helpdesk/Index', [
            'featuredArticles' => $this->helpdeskService->getFeaturedArticles(4),
            'recentArticles' => $this->helpdeskService->getRecentArticles(6),
            'popularArticles' => $this->helpdeskService->getPopularArticles(5),
            'categories' => $this->helpdeskService->getCategoryTree(),
            'tags' => $this->helpdeskService->getTags(),
            'categorySlug' => null,
        ]);
    }

    public function show(string $slug)
    {
        $article = $this->helpdeskService->getArticleBySlug($slug);

        if (! $article) {
            abort(404);
        }

        return Inertia::render('Helpdesk/Show', [
            'article' => $article,
            'relatedArticles' => $this->helpdeskService->getRelatedArticles($article->id),
            'categoryPath' => $article->category ? $this->buildCategoryPath($article->category) : [],
            'categories' => $this->helpdeskService->getCategoryTree(),
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $filters = $request->only(['category_id']);

        return Inertia::render('Helpdesk/Search', [
            'query' => $query,
            'results' => $this->helpdeskService->searchArticles($query, $filters),
            'categories' => $this->helpdeskService->getCategories(),
        ]);
    }

    private function buildCategoryPath($category): array
    {
        $path = [];
        $cat = $category;
        while ($cat) {
            $path[] = ['label' => $cat->name, 'slug' => $cat->slug];
            $cat = $cat->parent;
        }

        return array_reverse($path);
    }

    public function feedback(StoreArticleFeedbackRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()?->id;

        $this->helpdeskService->submitFeedback($data);

        return back()->with('success', 'Thank you for your feedback.');
    }
}
