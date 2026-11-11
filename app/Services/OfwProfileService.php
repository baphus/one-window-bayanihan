<?php

namespace App\Services;

use App\Models\User;

class OfwProfileService
{
    /**
     * Account identity only. Case facts (address, employment, next of kin)
     * stay on each case snapshot and are never copied back here.
     */
    public function updateAccount(User $user, array $data): void
    {
        $user->fill(array_intersect_key($data, array_flip([
            'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'sex', 'contact_number',
        ])));
        $user->save();
    }
}
