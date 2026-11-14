<?php

namespace App\Services\Export\Queries\Concerns;

use App\Support\CategoryFilter;

trait FiltersCaseCategories
{
    private function categoryNamesExpression(string $caseAlias): string
    {
        return "(SELECT STRING_AGG(DISTINCT cc.name, ', ' ORDER BY cc.name) FROM case_file_case_category ccc JOIN case_categories cc ON cc.id = ccc.case_category_id WHERE ccc.case_id = {$caseAlias}.id)";
    }

    private function applyCategoryFilter($query, string $caseAlias, array $filters)
    {
        $categoryIds = CategoryFilter::fromArray($filters)->ids();
        if (! $categoryIds) {
            return $query;
        }

        return $query->whereExists(function ($sub) use ($categoryIds, $caseAlias) {
            $sub->selectRaw('1')->from('case_file_case_category AS category_case')
                ->whereColumn('category_case.case_id', $caseAlias.'.id')
                ->whereIn('category_case.case_category_id', $categoryIds);
        });
    }
}
