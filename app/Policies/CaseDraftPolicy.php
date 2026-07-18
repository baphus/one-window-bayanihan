<?php

namespace App\Policies;

use App\Models\CaseDraft;
use App\Models\User;

final class CaseDraftPolicy
{
    public function create(User $user): bool
    {
        return $user->isCaseManager();
    }

    public function view(User $user, CaseDraft $draft): bool
    {
        return $this->owns($user, $draft);
    }

    public function save(User $user, CaseDraft $draft): bool
    {
        return $this->owns($user, $draft);
    }

    public function publish(User $user, CaseDraft $draft): bool
    {
        return $this->owns($user, $draft);
    }

    public function discard(User $user, CaseDraft $draft): bool
    {
        return $this->owns($user, $draft);
    }

    private function owns(User $user, CaseDraft $draft): bool
    {
        return $user->isCaseManager() && (string) $draft->owner_id === (string) $user->id;
    }
}
