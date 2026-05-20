<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHelpdeskArticleRequest;
use App\Http\Requests\UpdateHelpdeskArticleRequest;
use App\Models\HelpdeskArticle;
use App\Services\HelpdeskService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HelpdeskArticleController extends Controller
{
    public function __construct(
        private readonly HelpdeskService $helpdeskService,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('Admin/Helpdesk/Index', [
            'articles' => $this->helpdeskService->getArticlesForAdmin(
                $request->only(['search', 'status', 'category_id', 'featured', 'trashed'])
            ),
            'filters' => $request->only(['search', 'status', 'category_id', 'featured', 'trashed']),
            'categories' => $this->helpdeskService->getAllCategoriesForAdmin(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Helpdesk/Edit', [
            'article' => null,
            'categories' => $this->helpdeskService->getAllCategoriesForAdmin(),
            'tags' => $this->helpdeskService->getTags(),
        ]);
    }

    public function store(StoreHelpdeskArticleRequest $request)
    {
        $article = $this->helpdeskService->createArticle(
            $request->validated(),
            $request->user()->id,
        );

        return redirect()
            ->route('admin.helpdesk.articles.edit', $article)
            ->with('success', 'Article created successfully.');
    }

    public function edit(string $id)
    {
        $article = HelpdeskArticle::with(['category', 'tags', 'author'])->findOrFail($id);

        return Inertia::render('Admin/Helpdesk/Edit', [
            'article' => $article,
            'categories' => $this->helpdeskService->getAllCategoriesForAdmin(),
            'tags' => $this->helpdeskService->getTags(),
        ]);
    }

    public function update(UpdateHelpdeskArticleRequest $request, string $id)
    {
        $this->helpdeskService->updateArticle(
            $id,
            $request->validated(),
            $request->user()->id,
        );

        return redirect()
            ->route('admin.helpdesk.articles.edit', $id)
            ->with('success', 'Article updated successfully.');
    }

    public function destroy(string $id)
    {
        $this->helpdeskService->deleteArticle($id);

        return redirect()
            ->route('admin.helpdesk.articles.index')
            ->with('success', 'Article moved to trash.');
    }

    public function restore(string $id)
    {
        $this->helpdeskService->restoreArticle($id);

        return redirect()
            ->route('admin.helpdesk.articles.index')
            ->with('success', 'Article restored successfully.');
    }

    public function toggleFeatured(string $id)
    {
        $this->helpdeskService->toggleFeatured($id);

        return redirect()->back()->with('success', 'Featured status toggled.');
    }

    public function versions(string $id)
    {
        $article = HelpdeskArticle::with(['author'])->findOrFail($id);

        return Inertia::render('Admin/Helpdesk/Versions', [
            'article' => $article,
            'revisions' => $this->helpdeskService->getRevisions($id),
        ]);
    }
}
