<?php

namespace App\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class EncryptedDate implements CastsAttributes
{
    /**
     * Decrypt the stored ciphertext and cast to a Carbon instance.
     *
     * Falls back to parsing the raw value for existing plaintext data
     * that hasn't been through the data encryption migration yet.
     */
    public function get($model, string $key, $value, array $attributes): ?Carbon
    {
        $decrypted = (new EncryptedString)->get($model, $key, $value, $attributes);

        if ($decrypted === null) {
            return null;
        }

        return Carbon::parse($decrypted);
    }

    /**
     * Cast the date value to a string, then encrypt for storage.
     */
    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        return (new EncryptedString)->set($model, $key, (string) $value, $attributes);
    }
}
