<?php

namespace App\Services;

use App\Models\CaseFile;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for the two case identifiers.
 *
 * CaseService and IntakeService previously each carried their own private
 * generators. They had already drifted: IntakeService returned
 * strtoupper(bin2hex(random_bytes(4))) for the tracker — eight hex characters
 * with no OWBAP- prefix — under a docblock claiming it mirrored CaseService.
 * Every self-filed OFW received an identifier the tracking portal told them to
 * type differently. Duplication is what allowed that, so there is now one
 * implementation both services delegate to.
 *
 * Formats:
 *   case_number    OWB-{YEAR}-{NNNNN}   sequential per calendar year
 *   tracker_number OWBAP-XXXXXXX        random, client-facing
 *
 * The split is deliberate and worth preserving. case_number is sequential and
 * therefore enumerable, so it is never a lookup key; public tracking resolves
 * on tracker_number only, which is random. That is what keeps a predictable
 * reference from becoming an access-control problem (OWASP: predictable
 * identifiers / IDOR).
 */
class CaseNumberGenerator
{
    private const CASE_NUMBER_PAD = 5;

    private const TRACKER_LENGTH = 10;

    /**
     * Crockford base32: the digits and uppercase letters minus I, L, O and U.
     *
     * I/1 and O/0 are indistinguishable when an OFW reads a tracker to support
     * over the phone or copies it out of an SMS, and U is excluded to avoid
     * accidental profanity. Removing them costs a little entropy per character
     * and buys correctness in the one interaction that matters — a client trying
     * to find their own case.
     */
    private const TRACKER_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * Allocate the next case number for the current operating-timezone year.
     *
     * Allocation is one atomic statement against case_number_counters. There is
     * no read-modify-write and no advisory lock: ON CONFLICT DO UPDATE takes the
     * row lock, increments, and returns the new value in a single round trip.
     *
     * Numbers are allocated from the counter rather than from MAX(case_number),
     * so hard-deleting a case can never release its number to a later case and
     * leave two cases sharing one identifier in the audit trail.
     *
     * Note on padding: str_pad never truncates, so allocation past 99999 yields
     * OWB-{YEAR}-100000. Uniqueness and ordering still hold; only the documented
     * five-digit width is exceeded.
     */
    public function nextCaseNumber(): string
    {
        $year = $this->currentYear();

        $row = DB::selectOne(
            'INSERT INTO case_number_counters (year, last_number, created_at, updated_at)
             VALUES (?, 1, NOW(), NOW())
             ON CONFLICT (year) DO UPDATE
               SET last_number = case_number_counters.last_number + 1,
                   updated_at = NOW()
             RETURNING last_number',
            [$year]
        );

        $next = (int) $row->last_number;

        return sprintf('OWB-%d-%s', $year, str_pad((string) $next, self::CASE_NUMBER_PAD, '0', STR_PAD_LEFT));
    }

    /**
     * Allocate an unused tracker number.
     *
     * Drawn uniformly from an unambiguous 32-character alphabet using random_int,
     * which is CSPRNG-backed. Ten characters give 32^10, about 50 bits.
     *
     * The previous implementation used strtoupper(Str::random(7)). That drew from
     * 62 characters and then collapsed case, so each letter was twice as likely
     * as each digit — roughly 35.8 bits rather than the 36.2 a uniform 36^7 would
     * give. Non-uniformity there was an accident of implementation, not a choice.
     *
     * Retries on collision rather than relying solely on the unique index, so the
     * caller receives a usable value instead of an exception.
     */
    public function nextTrackerNumber(): string
    {
        $max = strlen(self::TRACKER_ALPHABET) - 1;

        do {
            $token = '';
            for ($i = 0; $i < self::TRACKER_LENGTH; $i++) {
                $token .= self::TRACKER_ALPHABET[random_int(0, $max)];
            }
            $tracker = 'OWBAP-'.$token;
        } while (CaseFile::withoutGlobalScopes()->where('tracker_number', $tracker)->exists());

        return $tracker;
    }

    /**
     * The calendar year in the jurisdiction's timezone, not UTC.
     *
     * Storage stays UTC, which is correct, but the case number is a records
     * reference expected to follow the local calendar year. Derived from UTC it
     * rolled over at 08:00 PHT on 1 January, so cases filed in Manila between
     * midnight and 08:00 carried the previous year.
     */
    private function currentYear(): int
    {
        return (int) now()
            ->timezone(config('app.operating_timezone', 'Asia/Manila'))
            ->format('Y');
    }
}
