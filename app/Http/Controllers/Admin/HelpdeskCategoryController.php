<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHelpdeskCategoryRequest;
use App\Services\HelpdeskService;
use Inertia\Inertia;

class HelpdeskCategoryController extends Controller
{
    public function __construct(
        private readonly HelpdeskService $helpdeskService,
    ) {}

    public function index()
    {
        return Inertia::render('Admin/Helpdesk/Categories', [
            'categories' => $this->helpdeskService->getAllCategoriesForAdmin(),
            'allCategories' => $this->helpdeskService->getAllCategoriesForAdmin(),
        ]);
    }

    public function store(StoreHelpdeskCategoryRequest $request)
    {
        $category = $this->helpdeskService->createCategory($request->validated());

        if ($request->input('_quick')) {
            return response()->json([
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'parent_id' => $category->parent_id,
                'icon' => $category->icon,
                'sort_order' => $category->sort_order,
                'articles_count' => 0,
                'children' => [],
            ]);
        }

        return redirect()
            ->route('admin.helpdesk.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function update(StoreHelpdeskCategoryRequest $request, string $id)
    {
        $this->helpdeskService->updateCategory($id, $request->validated());

        return redirect()
            ->route('admin.helpdesk.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(string $id)
    {
        $this->helpdeskService->deleteCategory($id);

        return redirect()
            ->route('admin.helpdesk.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
