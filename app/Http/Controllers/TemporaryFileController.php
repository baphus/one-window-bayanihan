<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemporaryFileController extends Controller
{
    /**
     * Stream a stored file behind an application-signed temporary URL.
     *
     * This is the local/private-disk equivalent of following a presigned
     * object-storage URL: StorageService hands out the link only after the
     * caller authorized the download, and the link itself is the capability —
     * it expires, and it may be opened in a new tab without a session, so no
     * auth or role check runs here. Only local disks are reachable, and the
     * disk and path both come from the signed route parameters, never from
     * unvalidated input.
     *
     * The disposition stays inline, exactly like the `GET` of a presigned
     * object-storage URL: the same link serves documents and avatar/logo
     * images rendered in <img>, so forcing an attachment download here would
     * break the latter. Reading a file that disappears between the existence
     * check and the read throws, and surfaces as a 500 rather than a 404.
     */
    public function __invoke(string $disk, string $path): StreamedResponse
    {
        abort_unless($this->isServableDisk($disk) && $this->isServablePath($path), 404);

        /** @var FilesystemAdapter $filesystem Local adapter for the whitelisted disk. */
        $filesystem = Storage::disk($disk);

        abort_unless($filesystem->exists($path), 404);

        return $filesystem->response($path, null, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Determine whether the disk is a configured local disk.
     *
     * Remote disks mint their own presigned URLs and must never be proxied
     * through the application; an unknown disk name is refused outright.
     */
    private function isServableDisk(string $disk): bool
    {
        $disks = config('filesystems.disks', []);

        return is_array($disks)
            && array_key_exists($disk, $disks)
            && is_array($disks[$disk])
            && ($disks[$disk]['driver'] ?? null) === 'local';
    }

    /**
     * Determine whether the signed path stays inside the disk root.
     */
    private function isServablePath(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '..')
            && ! str_contains($path, "\0")
            && ! str_contains($path, '\\');
    }
}
