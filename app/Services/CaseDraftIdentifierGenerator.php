<?php

namespace App\Services;

use App\Models\CaseFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use PDOException;

final class CaseDraftIdentifierGenerator implements CaseDraftIdentifierGeneratorContract
{
    private const MAX_ATTEMPTS = 10;

    public function generate(): array
    {
        $caseNumber = $this->generateUnique(
            fn (): string => 'CASE-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'case_number',
        );
        $trackerNumber = $this->generateUnique(
            fn (): string => 'OWBAP-'.strtoupper(Str::random(7)),
            'tracker_number',
        );

        return [$caseNumber, $trackerNumber];
    }

    /**
     * @param  callable(): string  $candidateFactory
     */
    private function generateUnique(callable $candidateFactory, string $column): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = $candidateFactory();

            if (! CaseFile::where($column, $candidate)->exists()) {
                return $candidate;
            }
        }

        // Surface exhaustion as the same retryable PostgreSQL conflict used by
        // the publisher when an insert loses a race with another publisher.
        $previous = new PDOException("Unable to generate a unique {$column}.", 23505);

        throw new QueryException(
            config('database.default'),
            "select exists for {$column}",
            [],
            $previous,
        );
    }
}
