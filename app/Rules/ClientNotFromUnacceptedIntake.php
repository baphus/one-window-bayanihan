<?php

namespace App\Rules;

use App\Models\Client;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a client that is not published (no linked account or no live OPEN
 * case). Hiding those people from the client picker stops the mistake being
 * made through the UI; this closes the request itself.
 *
 * @see Client::scopePublishedOnly()
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

        if (! Client::whereKey($client->id)->publishedOnly()->exists()) {
            $fail('This person is not a published client. Handle it in the intake queue rather than creating a new case.');
        }
    }
}
