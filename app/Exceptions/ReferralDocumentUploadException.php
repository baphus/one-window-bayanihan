<?php

namespace App\Exceptions;

/**
 * Thrown when a referral's document upload fails mid-request.
 *
 * The controller catches this to roll back the enclosing database
 * transaction so a failed upload never leaves a committed referral
 * behind.
 */
class ReferralDocumentUploadException extends SafeException
{
    public function __construct(string $userMessage, ?string $logMessage = null, ?\Throwable $previous = null)
    {
        parent::__construct('REFERRAL_DOCUMENT_UPLOAD_FAILED', $userMessage, $logMessage, $previous);
    }
}
