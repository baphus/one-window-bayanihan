<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\User;

class DefaultAgencyService
{
    public function getDefaultAgency(): ?Agency
    {
        return Agency::where('is_default', true)->first();
    }

    public function assignDefaultAgency(User $user): User
    {
        if ($user->agcy_id === null) {
            $default = $this->getDefaultAgency();
            if ($default) {
                $user->agcy_id = $default->id;
                $user->save();
            }
        }

        return $user;
    }
}
