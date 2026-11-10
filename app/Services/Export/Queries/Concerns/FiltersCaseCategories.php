<?php

namespace App\Services\Export\Queries\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\CategoryFilter;
use Illuminate\Support\Facades\DB;

trait FiltersCaseCategories
{
    private function categoryNamesExpression(string $caseAlias, ?User $user = null): string
    {
        if ($user?->role === UserRole::AGENCY->value && ! $user->agcy_id) {
            return 'NULL';
        }
        $agency = $user?->role === UserRole::AGENCY->value
            ? ' AND r.agcy_id = '.DB::connection()->getPdo()->quote($user->agcy_id ?? '')
            : '';

        return "(SELECT STRING_AGG(DISTINCT cc.name, ', ' ORDER BY cc.name) FROM referrals r JOIN case_categories cc ON cc.id = r.category_id WHERE r.case_id = {$caseAlias}.id AND r.is_deleted = false AND r.deleted_at IS NULL{$agency})";
    }

    private function applyCategoryFilter($query, string $caseAlias, array $filters, ?User $user = null)
    {
        $categoryIds = CategoryFilter::fromArray($filters)->ids();
        if (! $categoryIds) {
            return $query;
        }

        return $query->whereExists(function ($sub) use ($categoryIds, $caseAlias, $user) {
            $sub->selectRaw('1')->from('referrals AS category_referral')
                ->whereColumn('category_referral.case_id', $caseAlias.'.id')
                ->where('category_referral.is_deleted', false)->whereNull('category_referral.deleted_at')
                ->whereIn('category_referral.category_id', $categoryIds);
            if ($user?->role === UserRole::AGENCY->value) {
                $sub->where('category_referral.agcy_id', $user->agcy_id);
            }
        });
    }
}
