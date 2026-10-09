<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResolvesPerPage
{
    /**
     * Resolve a safe page size from the request.
     *
     * Only explicit allow-list values are honoured; anything else
     * (including zero or negatives, which would break pagination)
     * falls back to the endpoint's default.
     */
    private function perPage(Request $request, int $default, array $allowed = [15, 25, 50, 100]): int
    {
        $requested = (int) $request->input('per_page', $default);

        return in_array($requested, $allowed, true) ? $requested : $default;
    }
}
