<?php

namespace App\Services\Export\Queries\Concerns;

use App\Enums\UserRole;
use App\Models\User;

trait ScopesForExportUser
{
    private function isAdmin(?User $user): bool
    {
        return $user === null
            || $user->role === UserRole::ADMIN->value
            || $user->role === UserRole::CASE_MANAGER->value;
    }
}
