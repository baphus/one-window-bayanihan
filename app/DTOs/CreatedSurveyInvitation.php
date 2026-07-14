<?php

namespace App\DTOs;

use App\Models\SurveyInvitation;

readonly class CreatedSurveyInvitation
{
    public function __construct(
        public SurveyInvitation $invitation,
        public string $rawToken,
    ) {}
}
