<?php

namespace App\Services\Export\Queries\Concerns;

use App\Support\CategoryFilter;

trait FiltersCaseCategories
{
    private function categoryNamesExpression(string $caseAlias): string
    {
        return "COALESCE((SELECT STRING_AGG(DISTINCT cc.name, ', ' ORDER BY cc.name) FROM case_category ca JOIN case_categories cc ON cc.id = ca.case_category_id WHERE ca.case_id = {$caseAlias}.id), (SELECT name FROM case_categories WHERE id = {$caseAlias}.category_id))";
    }

    /** @return array<int, string> */
    private function applyCategoryFilter($query, string $caseAlias, array $filters)
    {
        $categoryIds = CategoryFilter::fromArray($filters)->ids();
        if (! $categoryIds) {
            return $query;
        }

        return $query->where(function ($category) use ($categoryIds, $caseAlias) {
            $category->whereIn($caseAlias.'.category_id', $categoryIds);
            $category->orWhereExists(fn ($sub) => $sub->selectRaw('1')
                ->from('case_category AS category_assignment')
                ->whereColumn('category_assignment.case_id', $caseAlias.'.id')
                ->whereIn('category_assignment.case_category_id', $categoryIds));
        });
    }
}
