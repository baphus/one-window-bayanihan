<?php

namespace App\Exceptions;

class SafeException extends \RuntimeException
{
    public readonly string $errorCode;

    public readonly string $userMessage;

    public ?string $incidentId;

    public function __construct(
        string $errorCode,
        string $userMessage,
        ?string $logMessage = null,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = $errorCode;
        $this->userMessage = $userMessage;
        $this->incidentId = null;

        parent::__construct($logMessage ?? $userMessage, 0, $previous);
    }

    public function setIncidentId(string $incidentId): void
    {
        $this->incidentId = $incidentId;
    }
}
