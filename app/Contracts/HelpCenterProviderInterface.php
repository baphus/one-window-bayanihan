<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface HelpCenterProviderInterface
{
    public function search(string $query, array $filters = []): Collection;

    public function getById(string $id): mixed;

    public function getBySlug(string $slug): mixed;
}
