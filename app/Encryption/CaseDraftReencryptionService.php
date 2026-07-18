<?php

namespace App\Encryption;

use App\Models\CaseDraft;
use Illuminate\Support\Facades\DB;

final class CaseDraftReencryptionService
{
    public function __construct(private readonly VersionedPayloadEncryptor $encryptor) {}

    /**
     * Re-encrypt a draft only when its locked database value still matches
     * the caller's snapshot. The transaction covers read, check, and write.
     */
    public function reencryptIfUnchanged(CaseDraft|string $draft, string $expectedCiphertext, bool $dryRun = false): bool
    {
        $id = $draft instanceof CaseDraft ? $draft->getKey() : $draft;

        return DB::transaction(function () use ($id, $expectedCiphertext, $dryRun): bool {
            $locked = CaseDraft::query()->whereKey($id)->lockForUpdate()->first();

            if ($locked === null
                || ! $locked->isEditing()
                || $locked->getRawOriginal('payload_encrypted') !== $expectedCiphertext) {
                return false;
            }

            if ($this->encryptor->isCurrentKey($expectedCiphertext)) {
                return false;
            }

            $replacement = $this->encryptor->reencrypt($expectedCiphertext);
            if ($dryRun) {
                return true;
            }

            // Preserve the original snapshot so only payload_encrypted is dirty.
            $locked->setRawAttributes([
                ...$locked->getAttributes(),
                'payload_encrypted' => $replacement,
            ]);
            $locked->timestamps = false;
            $locked->saveQuietly();

            return true;
        });
    }
}
