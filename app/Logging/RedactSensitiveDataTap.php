<?php

namespace App\Logging;

use Illuminate\Log\Logger;

class RedactSensitiveDataTap
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(new RedactSensitiveData);
    }
}
