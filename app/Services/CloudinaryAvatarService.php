<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryAvatarService
{
    /**
     * Content MIME types the product accepts for avatars and logos. Must keep
     * matching the `mimes:` set in the uploading FormRequests (ProfilePictureRequest:
     * jpeg, png, gif, webp); a file whose DECODED content sniffs to anything
     * else is a polyglot or a spoofed extension and is rejected before upload.
     */
    private const ALLOWED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Fixed, credential-free user-facing message used when the Cloudinary API
     * call fails (revoked/invalid key, outage, transport error). Deliberately
     * NEVER replaced with the raw upstream message, which embeds the API key
     * and other configuration details.
     */
    private const UPLOAD_FAILURE_MESSAGE = 'The image could not be uploaded. Please try again later.';

    private Cloudinary $cloudinary;

    public function __construct()
    {
        $cloudinaryUrl = config('services.cloudinary.url');

        if (! $cloudinaryUrl) {
            throw new \RuntimeException('Cloudinary is not configured. Set CLOUDINARY_URL in your environment.');
        }

        $this->cloudinary = new Cloudinary($cloudinaryUrl);
    }

    public function uploadImage(UploadedFile $file, string $folder, string $publicId): string
    {
        $this->validateImageFile($file);

        try {
            $result = $this->performUpload($file->getRealPath(), [
                'folder' => $folder,
                'public_id' => $publicId,
                'overwrite' => true,
                'resource_type' => 'image',
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException(self::UPLOAD_FAILURE_MESSAGE, 0, $e);
        }

        return $result['secure_url'] ?? throw new \RuntimeException('Cloudinary upload did not return a secure URL.');
    }

    /**
     * Executes the Cloudinary upload API call.
     *
     * Seam for tests: a test subclass overrides this to simulate Cloudinary
     * failures (invalid credentials, outage, transport errors) without any
     * network access. Failures of any exception type thrown here are
     * converted by uploadImage() into a RuntimeException carrying a fixed,
     * credential-free message — never the raw upstream message.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function performUpload(string $path, array $options): array
    {
        return (array) $this->cloudinary->uploadApi()->upload($path, $options);
    }

    /**
     * Executes the Cloudinary destroy API call.
     *
     * Seam for tests: a test subclass overrides this to simulate Cloudinary
     * failures without any network access. Callers are responsible for their
     * own failure handling (deleteByUrl() treats it as best-effort).
     *
     * @param  array<string, mixed>  $options
     */
    protected function performDestroy(string $publicId, array $options): void
    {
        $this->cloudinary->uploadApi()->destroy($publicId, $options);
    }

    /**
     * Server-side content validation performed INDEPENDENTLY of any
     * FormRequest rules, so every caller (profile picture, agency logo,
     * client photo) gets the same guarantees: non-empty file, decodable
     * image content, content MIME in the allowlist (a spoofed .jpg name with
     * text/plain or script content is rejected here) and a 32x32 minimum.
     *
     * @throws \RuntimeException When the file fails any check.
     */
    public function validateImageFile(UploadedFile $file): void
    {
        $realPath = $file->getRealPath();

        if (! $realPath || ! file_exists($realPath)) {
            throw new \RuntimeException('Uploaded file does not have a valid path.');
        }

        if (filesize($realPath) === 0) {
            throw new \RuntimeException('Uploaded file is empty.');
        }

        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false) {
            throw new \RuntimeException('Uploaded file is not a valid image.');
        }

        // The client-declared MIME/extension can be spoofed; the decoded
        // content is authoritative for what the file actually is.
        $detectedMime = $imageInfo['mime'] ?? null;
        if (! is_string($detectedMime) || ! in_array($detectedMime, self::ALLOWED_IMAGE_MIME_TYPES, true)) {
            throw new \RuntimeException('Uploaded file content is not an allowed image type (JPEG, PNG, GIF, or WebP).');
        }

        // Reject images smaller than 32x32 (likely corrupt/placeholder)
        if ($imageInfo[0] < 32 || $imageInfo[1] < 32) {
            throw new \RuntimeException('Uploaded image dimensions are too small.');
        }
    }

    /**
     * Best-effort asset deletion: a failure (expired/revoked credentials,
     * outage) must never abort the caller's flow — ClientController::storeAvatar
     * deletes the previous avatar BEFORE uploading the new one — so failures
     * are logged and swallowed.
     */
    public function deleteByUrl(?string $url): void
    {
        $publicId = $this->publicIdFromUrl($url);

        if (! $publicId) {
            return;
        }

        try {
            $this->performDestroy($publicId, [
                'resource_type' => 'image',
                'invalidate' => true,
            ]);
        } catch (\Throwable $e) {
            // Deliberately credential-free: no upstream message, no API key,
            // no CLOUDINARY_URL — only the public asset identifier.
            Log::warning('Cloudinary avatar delete failed and was skipped (best effort).', [
                'public_id' => $publicId,
            ]);
        }
    }

    private function publicIdFromUrl(?string $url): ?string
    {
        if (! $url || ! str_contains($url, 'res.cloudinary.com') || ! str_contains($url, '/image/upload/')) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! $path) {
            return null;
        }

        $parts = explode('/image/upload/', $path, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $publicPath = $parts[1];
        $segments = explode('/', $publicPath);
        if (isset($segments[0]) && preg_match('/^v\d+$/', $segments[0])) {
            array_shift($segments);
        }

        $publicPath = implode('/', $segments);
        $extensionPosition = strrpos($publicPath, '.');

        return $extensionPosition === false
            ? $publicPath
            : substr($publicPath, 0, $extensionPosition);
    }
}
