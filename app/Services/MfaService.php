<?php

namespace App\Services;

use Illuminate\Support\Str;

class MfaService
{
    private function getRecoveryKey(): string
    {
        return config('app.key');
    }

    public function hashRecoveryCode(string $code): string
    {
        return hash_hmac('sha256', $code, $this->getRecoveryKey());
    }

    /**
     * Generate 8 new recovery codes in plaintext.
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(
                Str::random(4).'-'.Str::random(4).'-'.Str::random(4)
            );
        }

        return $codes;
    }

    /**
     * Hash an array of plaintext recovery codes for storage.
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn ($code) => $this->hashRecoveryCode($code), $codes);
    }

    /**
     * Verify a plaintext code against stored hashed codes.
     */
    public function verifyRecoveryCode(string $code, array $storedHashes): bool
    {
        $hash = $this->hashRecoveryCode($code);

        return in_array($hash, $storedHashes);
    }

    /**
     * Remove a used recovery code (by its plaintext) from stored hashed codes.
     */
    public function removeUsedCode(string $code, array $storedHashes): array
    {
        $hash = $this->hashRecoveryCode($code);

        return array_values(array_filter(
            $storedHashes,
            fn ($h) => $h !== $hash
        ));
    }
}
