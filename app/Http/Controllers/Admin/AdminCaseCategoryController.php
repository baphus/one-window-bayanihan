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

    public function destroy(Request $request, string $id)
    {
        $usageCount = 0;

        DB::transaction(function () use ($request, $id, &$usageCount) {
            // Case mutations lock cases before categories. Keep the same
            // order here to avoid deadlocks with concurrent assignments.
            $caseIds = $this->categoryCaseIds($id);

            if ($caseIds->isNotEmpty()) {
                DB::table('cases AS c')
                    ->whereIn('c.id', $caseIds)
                    ->orderBy('c.id')
                    ->lockForUpdate()
                    ->get(['c.id']);
            }

            $category = CaseCategory::whereKey($id)->lockForUpdate()->firstOrFail();
            $usageCount = $this->categoryUsageCount($category->id);

            if ($usageCount > 0) {
                return;
            }

            $category->is_active = false;
            $category->deleted_by = $request->user()->id;
            $category->delete();
        });

        if ($usageCount > 0) {
            return redirect()->route('admin.case-categories.index')
                ->with('error', 'Cannot delete category: '.$usageCount.' case(s) are using it.');
        }

        return redirect()->route('admin.case-categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    private function categoryUsageCount(string $categoryId): int
    {
        return (int) DB::table('case_category AS cc')
            ->join('cases AS c', 'c.id', '=', 'cc.case_id')
            ->where('cc.case_category_id', $categoryId)
            ->where('c.is_deleted', false)
            ->distinct()
            ->count('cc.case_id');
    }

    private function categoryCaseIds(string $categoryId)
    {
        return DB::table('case_category AS cc')
            ->where('cc.case_category_id', $categoryId)
            ->orderBy('cc.case_id')
            ->pluck('cc.case_id')
            ->unique()
            ->values();
    }
}
