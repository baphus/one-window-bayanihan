<?php

namespace App\Services\Chatbot;

use App\Models\User;

final class ChatbotAudience
{
    public function __construct(private readonly ?string $role = null) {}

    public static function forUser(?User $user): self
    {
        return new self($user?->role);
    }

    public function groups(): ?array
    {
        return match ($this->role) {
            'ADMIN' => null,
            'CASE_MANAGER' => ['OFW & Public', 'General', 'Case Managers'],
            'AGENCY' => ['OFW & Public', 'General', 'Agency Focal Persons'],
            default => ['OFW & Public', 'General'],
        };
    }

    public function allows(string $group): bool
    {
        return $this->groups() === null || in_array($group, $this->groups(), true);
    }
}
