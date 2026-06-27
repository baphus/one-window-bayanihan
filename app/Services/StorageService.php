<?php

namespace App\Services;

use App\DTOs\FileStoreResult;
use App\Services\Contracts\MalwareScannerInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    public function __construct(
        private readonly MalwareScannerInterface $malwareScanner,
    ) {}

    /**
     * Store an uploaded file to the supabase disk.
     *
     * @param  UploadedFile  $file  The uploaded file instance.
     * @param  string  $directory  The directory within the disk to store the file.
     * @param  array|null  $options  Optional storage options (e.g., visibility).
     */
    public function store(UploadedFile $file, string $directory, ?array $options = []): FileStoreResult
    {
        if (! $file->isValid()) {
            return new FileStoreResult(
                path: '',
                originalName: $file->getClientOriginalName(),
                storedName: '',
                type: $file->getMimeType() ?? 'application/octet-stream',
                size: $file->getSize() ?? 0,
                success: false,
                error: $file->getErrorMessage() ?: 'Invalid file upload.',
            );
        }

        // Malware scan on the uploaded temp file before persisting to disk
        $scanResult = $this->malwareScanner->scan($file->getPathname());
        if (! $scanResult->isClean()) {
            return new FileStoreResult(
                path: '',
                originalName: $file->getClientOriginalName(),
                storedName: '',
                type: $file->getMimeType() ?? 'application/octet-stream',
                size: $file->getSize() ?? 0,
                success: false,
                error: $scanResult->getReason() ?? 'File rejected by malware scanner.',
            );
        }

        $storedName = Str::uuid().'.'.$file->guessExtension();
        $path = $file->storeAs($directory, $storedName, 'supabase');

        if ($path === false) {
            return new FileStoreResult(
                path: '',
                originalName: $file->getClientOriginalName(),
                storedName: $storedName,
                type: $file->getMimeType() ?? 'application/octet-stream',
                size: $file->getSize() ?? 0,
                success: false,
                error: 'Failed to store file.',
            );
        }

        return new FileStoreResult(
            path: $path,
            originalName: $file->getClientOriginalName(),
            storedName: $storedName,
            type: $file->getMimeType() ?? 'application/octet-stream',
            size: $file->getSize() ?? 0,
        );
    }

    /**
     * Delete a file from the supabase disk.
     *
     * @param  string  $path  The full storage path to delete.
     * @return bool True on success, false on failure or exception.
     */
    public function delete(string $path): bool
    {
        try {
            return Storage::disk('supabase')->delete($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Generate a temporary (signed) URL for a private file.
     *
     * @param  string  $path  The storage path of the file.
     * @param  int  $ttlHours  Time-to-live in hours (default: 24).
     * @return string|null The temporary URL, or null on failure.
     */
    public function temporaryUrl(string $path, int $ttlHours = 24): ?string
    {
        try {
            return Storage::disk('supabase')->temporaryUrl(
                $path,
                now()->addHours($ttlHours)
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Map of file extensions to their expected MIME types for content-sniffing validation.
     */
    private const EXTENSION_MIME_MAP = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];

    /**
     * Validate an uploaded file against context-specific rules.
     *
     * Reads allowed MIME types (as extensions) and max file size from
     * config('file-uploads.<context>'). Returns an array of error messages;
     * an empty array means the file is valid.
     *
     * @param  UploadedFile  $file  The uploaded file instance.
     * @param  string  $context  The validation context key (default: 'default').
     * @return array<string> List of validation error messages.
     */
    public function validate(UploadedFile $file, string $context = 'default'): array
    {
        $errors = [];
        $config = config('file-uploads.'.$context);

        if ($config === null) {
            return ["No validation configuration found for context: '{$context}'."];
        }

        // Check allowed file types (extensions from config)
        $allowedTypes = $config['mimes'] ?? [];
        if (! empty($allowedTypes)) {
            $extension = strtolower($file->guessExtension());
            if (! in_array($extension, $allowedTypes, true)) {
                $errors[] = "File type '{$extension}' is not allowed. Allowed types: ".implode(', ', $allowedTypes).'.';
            }
        }

        // Server-side MIME content-sniffing check (defense in depth — augments extension check above)
        if (! empty($allowedTypes)) {
            // Build a flat list of allowed MIME types from the allowed extensions
            $allowedMimes = $config['allowed_mime_types'] ?? [];
            if (empty($allowedMimes)) {
                foreach ($allowedTypes as $ext) {
                    $ext = strtolower($ext);
                    if (isset(self::EXTENSION_MIME_MAP[$ext])) {
                        $allowedMimes = array_merge($allowedMimes, self::EXTENSION_MIME_MAP[$ext]);
                    }
                }
                $allowedMimes = array_unique($allowedMimes);
            }

            if (! empty($allowedMimes)) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->file($file->getPathname());

                if (! in_array($detectedMime, $allowedMimes, true)) {
                    $errors[] = "File content (MIME: {$detectedMime}) does not match any allowed type.";
                }
            }
        }

        // Check file size (config stores max_size in KB)
        $maxSizeKb = (int) ($config['max_size'] ?? 10240);
        $maxSizeBytes = $maxSizeKb * 1024;
        if ($file->getSize() > $maxSizeBytes) {
            $errors[] = "File exceeds maximum allowed size of {$maxSizeKb} KB.";
        }

        return $errors;
    }
}
