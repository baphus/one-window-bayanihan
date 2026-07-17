<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminCaseCategoryController extends Controller
{
    public function index()
    {
        $categories = CaseCategory::orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function (CaseCategory $category) {
                $category->case_files_count = $this->categoryUsageCount($category->id);
            });

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
        $category = CaseCategory::findOrFail($id);
        $usageCount = $this->categoryUsageCount($category->id);

        if ($usageCount > 0) {
            return redirect()->route('admin.case-categories.index')
                ->with('error', 'Cannot delete category: '.$usageCount.' case(s) are using it.');
        }

        $category->update(['is_deleted' => true, 'is_active' => false]);

        return redirect()->route('admin.case-categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    private function categoryUsageCount(string $categoryId): int
    {
        return (int) DB::table('cases AS c')
            ->where('c.is_deleted', false)
            ->where(function ($query) use ($categoryId) {
                $query->where('c.category_id', $categoryId)
                    ->orWhereExists(function ($pivot) use ($categoryId) {
                        $pivot->selectRaw('1')
                            ->from('case_category AS cc')
                            ->whereColumn('cc.case_id', 'c.id')
                            ->where('cc.case_category_id', $categoryId);
                    });
            })
            ->distinct('c.id')
            ->count('c.id');
    }
}
