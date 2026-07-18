<?php

namespace App\Exceptions;

use RuntimeException;

final class CaseDraftPayloadDecryptionException extends RuntimeException
{
    public function __construct(string $keyId, ?\Throwable $previous = null)
    {
        parent::__construct("Case draft payload could not be decrypted with key [{$keyId}].", 0, $previous);
    }
}
