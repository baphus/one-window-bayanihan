<?php

namespace App\Rules;

use App\Models\Client;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a client who is only in the system because of a self-filed intake that
 * nobody has reviewed yet.
 *
 * Hiding those people from the client picker stops the mistake being made through
 * the UI; this closes the request itself. Attaching a real case to an unverified
 * submission would carry unchecked identity data into a live case, and the
 * accepted intake would then be a duplicate of it.
 *
 * @see Client::isAwaitingIntakeReview()
 */
final class ClientNotAwaitingIntakeReview implements ValidationRule
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

        if ($client->isAwaitingIntakeReview()) {
            $fail('This person has a self-filed request awaiting review. Review it in the intake queue instead of creating a new case.');
        }
    }
}
