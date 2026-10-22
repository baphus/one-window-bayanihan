<?php

namespace App\Models\Concerns;

use App\Services\StorageService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

trait HasAvatar
{
    /**
     * Get the avatar URL as a signed temporary URL from the private disk.
     * Falls back gracefully for legacy data (already absolute URLs) and null values.
     */
    public function getAvatarUrlAttribute(?string $value): ?string
    {
        return $this->signedPrivateMediaUrl($value, 'HasAvatar: failed to sign avatar URL');
    }

    /**
     * Get the logo URL as a signed temporary URL from the private disk.
     * Same behavior as getAvatarUrlAttribute but reads from the logo_url column.
     */
    public function getLogoUrlAttribute(?string $value): ?string
    {
        return $this->signedPrivateMediaUrl($value, 'HasAvatar: failed to sign logo URL');
    }

    /**
     * Sign a private-disk path as a short-lived URL.
     *
     * Delegated to StorageService so the private disk — which, unlike object
     * storage, cannot mint a presigned URL of its own — is served by the same
     * application-signed streaming route as every other file.
     *
     * @param  string|null  $value  Raw column value.
     * @param  string  $logContext  Message used if signing is impossible.
     */
    private function signedPrivateMediaUrl(?string $value, string $logContext): ?string
    {
        if (! $value) {
            return null;
        }

        // Already a full URL — return as-is (legacy data or external URL)
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        // Strip /storage/ prefix from legacy data that stored the public symlink path
        $path = ltrim($value, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        try {
            return app(StorageService::class)->temporaryUrlUntil($path, now()->addMinutes(5), 'private');
        } catch (RuntimeException $e) {
            Log::debug($logContext, ['path' => $path, 'exception' => $e->getMessage()]);

            return null;
        }
    }
}
