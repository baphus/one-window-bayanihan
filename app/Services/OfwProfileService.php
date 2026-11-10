<?php

namespace App\Services;

use App\Models\User;

class OfwProfileService
{
    public function updateAccount(User $user, array $data): void
    {
        if (array_key_exists('contact_number', $data)) {
            $user->contact_number = $data['contact_number'];
            $user->save();
        }
    }
}
