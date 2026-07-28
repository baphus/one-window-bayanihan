<?php

namespace App\Rules;

use App\Models\Client;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a client who is only in the system because of a self-filed intake that
 * was never accepted — either still awaiting review, or rejected outright.
 *
 * Hiding those people from the client picker stops the mistake being made through
 * the UI; this closes the request itself. Attaching a real case to an unverified
 * submission would carry unchecked identity data into a live case, and if that
 * intake is later accepted the two records describe the same person twice.
 *
 * @see Client::hasOnlyUnacceptedIntake()
 */
final class ClientNotFromUnacceptedIntake implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) || ! is_string($value)) {
            return;
        }

        // A missing client is the `exists` rule's business, not this rule's.
        $client = Client::find($value);

        if ($client === null) {
            return;
        }

        if ($client->hasOnlyUnacceptedIntake()) {
            $fail('This person only has a self-filed request that has not been accepted. Handle it in the intake queue rather than creating a new case.');
        }
    }
}
