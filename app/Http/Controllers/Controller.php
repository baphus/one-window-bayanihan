<?php

namespace App\Http\Controllers;

use App\Services\StorageService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToReadFile;

abstract class Controller
{
    protected function inlinePreview(string $path, string $filename)
    {
        $disk = Storage::disk(app(StorageService::class)->diskName());
        try {
            $stream = $disk->readStream($path);
        } catch (FileNotFoundException|UnableToReadFile $exception) {
            abort(404, 'File not found or unavailable.');
        }
        if (! is_resource($stream)) {
            abort(404, 'File not found or unavailable.');
        }

        $prefix = fread($stream, 8192);
        if ($prefix === false) {
            fclose($stream);
            abort(404, 'File not found or unavailable.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($prefix) ?: 'application/octet-stream';
        if (! in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            fclose($stream);
            abort(415, 'Preview unavailable for this file type.');
        }
        if (! rewind($stream)) {
            fclose($stream);
            abort(404, 'File not found or unavailable.');
        }
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($filename)) ?: 'preview';

        return response()->stream(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
