<?php

namespace App\Services;

use App\Models\User;

class HelpdeskArticleService
{
    public function all(): array
    {
        return json_decode(file_get_contents(resource_path('helpdesk/articles.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function allowedArticles(?User $user): array
    {
        return array_values(array_filter($this->all(), fn (array $article) => self::allows($article['audience'] ?? 'ADMIN', $user?->role)));
    }

    public function findAllowed(string $slug, ?User $user): ?array
    {
        foreach ($this->allowedArticles($user) as $article) {
            if ($article['slug'] === $slug) {
                return $article;
            }
        }

        return null;
    }

    public static function allows(string $audience, ?string $role): bool
    {
        return $role === 'ADMIN' || $audience === 'public'
            || ($audience === 'staff' && in_array($role, ['CASE_MANAGER', 'AGENCY'], true))
            || (in_array($audience, ['CASE_MANAGER', 'AGENCY'], true) && $audience === $role);
    }
}
