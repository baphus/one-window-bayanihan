<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final class CaseDraftPublished implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly string $caseId,
        public readonly ?string $recipientEmail,
        public readonly string $eventKey,
    ) {}
}
