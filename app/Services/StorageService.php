<?php

namespace App\Services;

use App\DTOs\FileStoreResult;
use App\Services\Contracts\MalwareScannerInterface;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class StorageService
{
    /**
     * Route that streams a file for an application-signed temporary URL. Used
     * as the fallback for disks whose driver cannot mint temporary URLs.
     */
    public const TEMP_FILE_ROUTE = 'files.temp';

    /**
     * The storage disk to use. Resolved from config so it's
     * environment-agnostic — swap to any S3-compatible or local disk
     * by changing FILESYSTEM_DISK in your .env.
     */
    private readonly string $disk;

    public function __construct(
        private readonly MalwareScannerInterface $malwareScanner,
    ) {
        $this->disk = config('filesystems.default', 'local');
    }

    /**
     * Get the configured storage disk name.
     */
    public function diskName(): string
    {
        return $this->disk;
    }

    /**
     * Store an uploaded file to the configured storage disk.
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

        try {
            $path = $file->storeAs($directory, $storedName, $this->disk);
        } catch (\Throwable $e) {
            return new FileStoreResult(
                path: '',
                originalName: $file->getClientOriginalName(),
                storedName: $storedName,
                type: $file->getMimeType() ?? 'application/octet-stream',
                size: $file->getSize() ?? 0,
                success: false,
                error: 'Storage error: '.$e->getMessage(),
            );
        }

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
            disk: $this->disk,
        );
    }

    /**
     * Delete a file from the configured storage disk.
     *
     * @param  string  $path  The full storage path to delete.
     * @return bool True on success, false on failure or exception.
     */
    public function delete(string $path): bool
    {
        try {
            return Storage::disk($this->disk)->delete($path);
        } catch (\Throwable $e) {
            Log::warning('StorageService: failed to delete file', [
                'disk' => $this->disk,
                'path' => $path,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate a temporary (signed) URL for a private file.
     *
     * Drivers that mint their own temporary URLs (S3-compatible object storage)
     * are asked first and own signing and expiry. A local disk cannot mint one
     * — Laravel's local adapter refuses outright — so the application signs the
     * link itself: the same expiring route the browser would follow for a
     * presigned object-storage URL, streamed by TemporaryFileController with
     * the same expiry.
     *
     * @param  string  $path  The storage path of the file.
     * @param  int  $ttlHours  Time-to-live in hours (default: 24).
     * @param  string|null  $disk  Disk holding the file (default: the configured disk).
     * @return string|null The temporary URL, or null only for an unknown/unconfigured disk.
     */
    public function temporaryUrl(string $path, int $ttlHours = 24, ?string $disk = null): ?string
    {
        return $this->temporaryUrlUntil($path, now()->addHours($ttlHours), $disk);
    }

    /**
     * Generate a temporary (signed) URL that expires at a given moment.
     *
     * Same fallback chain as temporaryUrl(), but for callers that need
     * sub-hour expiry (avatar and logo links) and can only express a deadline.
     *
     * @param  string  $path  The storage path of the file.
     * @param  DateTimeInterface  $expiresAt  The moment the link stops working.
     * @param  string|null  $disk  Disk holding the file (default: the configured disk).
     * @return string|null The temporary URL, or null only for an unknown/unconfigured disk.
     */
    public function temporaryUrlUntil(string $path, DateTimeInterface $expiresAt, ?string $disk = null): ?string
    {
        $diskName = $disk ?? $this->disk;

        try {
            /** @var FilesystemAdapter $filesystem Driver-specific disk, always a concrete adapter. */
            $filesystem = Storage::disk($diskName);
        } catch (InvalidArgumentException $e) {
            Log::warning('StorageService: unknown storage disk', [
                'disk' => $diskName,
                'path' => $path,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        try {
            return $filesystem->temporaryUrl($path, $expiresAt);
        } catch (RuntimeException $e) {
            // A local disk is the one driver expected to refuse here, because
            // there is no presigned URL it could mint — that refusal is the whole
            // reason this fallback exists, and it is the only thing that is
            // caught. The same exception from any other disk is a genuine
            // credential or IO failure, so it is rethrown and surfaces as a 500
            // rather than being flattened into a misleading "file not found".
            if (! $this->isLocalDisk($diskName)) {
                throw $e;
            }
        }

        return URL::temporarySignedRoute(self::TEMP_FILE_ROUTE, $expiresAt, [
            'disk' => $diskName,
            'path' => $path,
        ]);
    }

    /**
     * Determine whether the named disk is a local (filesystem) disk.
     *
     * Local disks are the ones the application has to serve itself: they are
     * rooted inside storage/ and cannot mint a presigned URL. Read from the same
     * config TemporaryFileController whitelists, so a link handed out here and
     * the endpoint that honours it can never disagree about what is servable.
     *
     * @param  string  $diskName  Configured disk name.
     */
    private function isLocalDisk(string $diskName): bool
    {
        return (config("filesystems.disks.{$diskName}.driver") ?? null) === 'local';
    }

    /**
     * Map of file extensions to their expected MIME types for content-sniffing validation.
     */
    private const EXTENSION_MIME_MAP = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        // finfo reports most CSV content as text/plain; text/csv only for well-formed input.
        'csv' => ['text/csv', 'text/plain'],
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
        $config = config('file-uploads.'.$context) ?? config('file-uploads.default');

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
            // Allowed MIME types are derived from the allowed extensions
            $allowedMimes = [];
            $unmappedExtensions = [];
            foreach ($allowedTypes as $ext) {
                $ext = strtolower($ext);
                if (isset(self::EXTENSION_MIME_MAP[$ext])) {
                    $allowedMimes = array_merge($allowedMimes, self::EXTENSION_MIME_MAP[$ext]);
                } else {
                    $unmappedExtensions[] = $ext;
                }
            }
            $allowedMimes = array_unique($allowedMimes);

            // Fail closed: an allowed extension with no MIME mapping cannot be
            // content-sniffed, so it must never slip past this check silently.
            foreach ($unmappedExtensions as $ext) {
                $errors[] = "File type '{$ext}' has no MIME mapping and cannot be content-validated.";
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
