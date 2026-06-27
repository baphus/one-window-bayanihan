<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasAvatar
{
    /**
     * Get the avatar URL as a signed temporary URL from the private disk.
     * Falls back gracefully for legacy data (already absolute URLs) and null values.
     */
    public function getAvatarUrlAttribute(?string $value): ?string
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

        return Storage::disk('private')->temporaryUrl($path, now()->addMinutes(5));
    }

    /**
     * Get the logo URL as a signed temporary URL from the private disk.
     * Same behavior as getAvatarUrlAttribute but reads from the logo_url column.
     */
    public function getLogoUrlAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        // Already a full URL — return as-is (legacy data or external URL)
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        // Strip /storage/ prefix from legacy data
        $path = ltrim($value, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        return Storage::disk('private')->temporaryUrl($path, now()->addMinutes(5));
    }
}
