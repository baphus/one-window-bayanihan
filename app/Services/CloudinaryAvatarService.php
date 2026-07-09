<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;

class CloudinaryAvatarService
{
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

        $result = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder' => $folder,
            'public_id' => $publicId,
            'overwrite' => true,
            'resource_type' => 'image',
        ]);

        return $result['secure_url'] ?? throw new \RuntimeException('Cloudinary upload did not return a secure URL.');
    }

    private function validateImageFile(UploadedFile $file): void
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

        // Reject images smaller than 32x32 (likely corrupt/placeholder)
        if ($imageInfo[0] < 32 || $imageInfo[1] < 32) {
            throw new \RuntimeException('Uploaded image dimensions are too small.');
        }
    }

    public function deleteByUrl(?string $url): void
    {
        $publicId = $this->publicIdFromUrl($url);

        if (! $publicId) {
            return;
        }

        $this->cloudinary->uploadApi()->destroy($publicId, [
            'resource_type' => 'image',
            'invalidate' => true,
        ]);
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
