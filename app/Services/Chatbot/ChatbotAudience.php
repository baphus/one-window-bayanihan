<?php

namespace App\Services\Chatbot;

use App\Enums\UserRole;
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
            UserRole::ADMIN->value => null,
            UserRole::CASE_MANAGER->value => ['OFW & Public', 'General', 'Case Managers'],
            UserRole::AGENCY->value => ['OFW & Public', 'General', 'Agency Focal Persons'],
            default => ['OFW & Public', 'General'],
        };
    }

    public function allows(string $group): bool
    {
        return $this->groups() === null || in_array($group, $this->groups(), true);
    }
}
