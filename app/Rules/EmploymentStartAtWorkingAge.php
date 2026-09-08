<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Keeps the reported employment start from pushing the worker below working
 * age. The start date must land on or after the minimum allowed DOB age —
 * currently 15, matching the StoreCaseRequest DOB floor — so a young worker
 * cannot report an implausibly long employment span.
 *
 * Only checked when a parseable date of birth is present; a blank DOB (partial
 * draft) leaves the strictly-chronological after_or_equal rule as the guard.
 */
final class EmploymentStartAtWorkingAge implements ValidationRule
{
    public function __construct(
        private readonly ?string $dateOfBirth = null,
        private readonly int $workingAge = 15,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || $this->dateOfBirth === null) {
            return;
        }

        try {
            $start = Carbon::parse($value);
            $earliest = Carbon::parse($this->dateOfBirth)->addYears($this->workingAge);
        } catch (\Throwable) {
            // Malformed dates are rejected by the 'date' rule, not here.
            return;
        }

        if ($start->lt($earliest)) {
            $fail('The employment start date is inconsistent with the client profile. Please review and correct it.');
        }
    }
}
