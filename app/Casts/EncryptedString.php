<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class EncryptedString implements CastsAttributes
{
    /**
     * Decrypt the stored ciphertext.
     *
     * Falls back to returning the raw value for existing plaintext data
     * that hasn't been through the data encryption migration yet.
     */
    public static function decrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // Value is plaintext — existing data before encryption migration.
            // Anything shaped like Laravel ciphertext (base64 JSON payload with
            // iv/value/mac) that fails to decrypt is corruption or a wrong key,
            // never legacy data: log it and re-throw instead of leaking it.
            if (self::isCiphertext($value)) {
                Log::warning('EncryptedString failed to decrypt a ciphertext value', ['exception' => $e->getMessage()]);

                throw $e;
            }

            return $value;
        }
    }

    /**
     * True when the value has the shape of Crypt::encryptString() output
     * (base64-encoded JSON with iv/value/mac keys). Such values are never
     * legacy plaintext.
     */
    private static function isCiphertext(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }

    /**
     * Decrypt the stored ciphertext.
     *
     * Falls back to returning the raw value for existing plaintext data
     * that hasn't been through the data encryption migration yet.
     */
    public function get($model, string $key, $value, array $attributes): ?string
    {
        return self::decrypt($value);
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
