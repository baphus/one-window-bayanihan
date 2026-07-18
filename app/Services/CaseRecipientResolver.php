<?php

namespace App\Services;

use App\Models\CaseFile;

final class CaseRecipientResolver
{
    public function resolve(CaseFile $case): ?string
    {
        $case->loadMissing(['client', 'selectedNok']);

        if (! $case->client || $case->client->is_deleted) {
            return null;
        }

        if ($case->client_type === CaseFile::CLIENT_TYPE_NEXT_OF_KIN) {
            if (! $case->selectedNok
                || $case->selectedNok->is_deleted
                || (string) $case->selectedNok->client_id !== (string) $case->client_id) {
                return null;
            }

            return $case->selectedNok->email;
        }

        return $case->client->email;
    }
}
