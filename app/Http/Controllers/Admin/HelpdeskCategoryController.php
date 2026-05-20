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
        $this->helpdeskService->createCategory($request->validated());

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
