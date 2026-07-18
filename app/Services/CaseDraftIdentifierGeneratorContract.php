<?php

namespace App\Services;

use Illuminate\Database\QueryException;

interface CaseDraftIdentifierGeneratorContract
{
    /**
     * @return array{0: string, 1: string}
     *
     * @throws QueryException When no available identifier pair is found.
     */
    public function generate(): array;
}
