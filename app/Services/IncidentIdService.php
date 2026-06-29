<?php

namespace App\Services;

use Ramsey\Uuid\Uuid;

class IncidentIdService
{
    /**
     * Generate a UUID v7 (or v4 fallback) for incident tracking.
     *
     * UUID v7 is time-ordered, which improves DB index performance
     * and provides chronological sorting. Falls back to UUID v4
     * if the ramsey/uuid version does not support uuid7().
     */
    public function generate(): string
    {
        if (method_exists(Uuid::class, 'uuid7')) {
            return Uuid::uuid7()->toString();
        }

        return Uuid::uuid4()->toString();
    }

    /**
     * Static convenience wrapper around generate().
     */
    public static function generateId(): string
    {
        return (new self)->generate();
    }

    /**
     * Format a UUID for human-readable display (first 8 hex chars).
     */
    public function formatForDisplay(string $id): string
    {
        return 'Ref: '.substr($id, 0, 8);
    }
}
