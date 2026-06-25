<?php

namespace App\Services;

use App\DTOs\FileStoreResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
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

        // Check file size (config stores max_size in KB)
        $maxSizeKb = (int) ($config['max_size'] ?? 10240);
        $maxSizeBytes = $maxSizeKb * 1024;
        if ($file->getSize() > $maxSizeBytes) {
            $errors[] = "File exceeds maximum allowed size of {$maxSizeKb} KB.";
        }

        return $errors;
    }
}
