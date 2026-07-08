<?php

namespace App\DTOs;

readonly class FileStoreResult
{
    public function __construct(
        public string $path,          // Full storage path
        public string $originalName,  // Original user filename
        public string $storedName,    // UUID-based stored filename
        public string $type,          // MIME type
        public int $size,             // File size in bytes
        public string $disk = '',     // Storage disk name (resolved at runtime)
        public bool $success = true,
        public ?string $error = null,
    ) {}
}
