<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaseCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminCaseCategoryController extends Controller
{
    public function index()
    {
        $categories = CaseCategory::withCount('caseFiles')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/CaseCategory/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:case_categories,name',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        CaseCategory::create($validated);

        return redirect()->route('admin.case-categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $category = CaseCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:case_categories,name,'.$id,
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return redirect()->route('admin.case-categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(string $id)
    {
        $category = CaseCategory::withCount('caseFiles')->findOrFail($id);

        if ($category->case_files_count > 0) {
            return redirect()->route('admin.case-categories.index')
                ->with('error', 'Cannot delete category: '.$category->case_files_count.' case(s) are using it.');
        }

        $category->update(['is_deleted' => true, 'is_active' => false]);

        return redirect()->route('admin.case-categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
