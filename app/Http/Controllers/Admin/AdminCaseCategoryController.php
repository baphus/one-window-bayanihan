<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminCaseCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = CaseCategory::with('suggestedAgencies:id,name,short,is_active')->withCount('referrals')->orderBy('sort_order')->orderBy('name');

        if ($request->boolean('show_deleted')) {
            $query->withTrashed();
        }

        $categories = $query->get()->each(function (CaseCategory $category) {
            $category->case_files_count = $this->categoryUsageCount($category->id);
        });

        return Inertia::render('Admin/CaseCategory/Index', [
            'categories' => $categories,
            'agencies' => Agency::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short']),
            'filters' => $request->only(['show_deleted']),
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
            'suggested_agency_ids' => 'sometimes|array',
            'suggested_agency_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('agencies', 'id')->where(fn ($query) => $query->where('is_active', true)->where('is_deleted', false)->whereNull('deleted_at'))],
        ]);

        DB::transaction(function () use ($validated) {
            $agencyIds = $validated['suggested_agency_ids'] ?? [];
            unset($validated['suggested_agency_ids']);
            $category = CaseCategory::create($validated);
            $this->syncSuggestedAgencies($category, $agencyIds);
        });

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
            'suggested_agency_ids' => 'sometimes|array',
            'suggested_agency_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('agencies', 'id')->where(fn ($query) => $query->where('is_active', true)->where('is_deleted', false)->whereNull('deleted_at'))],
        ]);

        DB::transaction(function () use ($category, $validated) {
            $agencyIds = $validated['suggested_agency_ids'] ?? null;
            unset($validated['suggested_agency_ids']);
            $category->update($validated);
            if ($agencyIds !== null) {
                $this->syncSuggestedAgencies($category, $agencyIds);
            }
        });

        return redirect()->route('admin.case-categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Request $request, string $id)
    {
        $usageCount = 0;

        DB::transaction(function () use ($request, $id, &$usageCount) {
            $category = CaseCategory::whereKey($id)->lockForUpdate()->firstOrFail();
            $usageCount = $category->referrals()->withTrashed()->distinct('case_id')->count('case_id') + $category->cases()->count();

            if ($usageCount > 0) {
                return;
            }

            $category->is_active = false;
            $category->deleted_by = $request->user()->id;
            $category->delete();
        });

        if ($usageCount > 0) {
            return redirect()->route('admin.case-categories.index')
                ->with('error', 'Cannot delete category: '.$usageCount.' case(s) or referral(s) are using it.');
        }

        return redirect()->route('admin.case-categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    public function reactivate(string $id)
    {
        $category = CaseCategory::withTrashed()->findOrFail($id);

        if ($category->is_active && ! $category->is_deleted) {
            return redirect()->route('admin.case-categories.index')
                ->with('error', 'Category is already active.');
        }

        $category->is_active = true;
        $category->is_deleted = false;
        $category->deleted_at = null;
        $category->saveQuietly();

        AuditLogger::log(AuditAction::REACTIVATE)
            ->byId(auth()->id())
            ->forEntity($category, $category->name)
            ->module(AuditModule::CASE_CATEGORY)
            ->context(['is_active' => false, 'is_deleted' => true], ['is_active' => true, 'is_deleted' => false])
            ->write();

        return redirect()->route('admin.case-categories.index')
            ->with('success', 'Category reactivated successfully.');
    }

    private function syncSuggestedAgencies(CaseCategory $category, array $agencyIds): void
    {
        $before = $category->suggestedAgencies()->pluck('agencies.id')->sort()->values()->all();
        $changes = $category->suggestedAgencies()->sync($agencyIds);

        if ($changes['attached'] !== [] || $changes['detached'] !== []) {
            sort($agencyIds);
            AuditLogger::log(AuditAction::UPDATE)
                ->byId(auth()->id())
                ->forEntity($category, $category->name)
                ->module(AuditModule::CASE_CATEGORY)
                ->context(['suggested_agency_ids' => $before], ['suggested_agency_ids' => $agencyIds])
                ->write();
        }
    }

    private function categoryUsageCount(string $categoryId): int
    {
        return DB::table('case_file_case_category')
            ->join('cases', 'cases.id', '=', 'case_file_case_category.case_id')
            ->where('case_file_case_category.case_category_id', $categoryId)
            ->where('cases.is_deleted', false)
            ->whereNull('cases.deleted_at')
            ->count();
    }
}
