<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

trait StoresGeneratedFile
{
    /**
     * Write generated file contents to the documents disk.
     *
     * The disk is configured with 'throw' => false, so a failed write returns
     * false rather than raising. Left unchecked, the job went on to mark the
     * document "completed" with a path and size while no file existed, and the
     * only symptom was a download that failed much later. Raising here lets
     * failed() record the real reason and notify the user.
     */
    private function storeGeneratedFile(string $path, string $contents): void
    {
        if (Storage::disk('supabase')->put($path, $contents) === false) {
            throw new RuntimeException(
                "Unable to write the generated file to storage at [{$path}]. Check the object storage credentials.",
            );
        }
    }
}
