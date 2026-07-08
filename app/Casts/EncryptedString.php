<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class EncryptedString implements CastsAttributes
{
    /**
     * Decrypt the stored ciphertext.
     *
     * Falls back to returning the raw value for existing plaintext data
     * that hasn't been through the data encryption migration yet.
     */
    public function get($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Value is plaintext — existing data before encryption migration
            return $value;
        }
    }

    /**
     * Encrypt the value for storage.
     */
    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypt::encryptString($value);
    }
}
