<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class CategoryFilter
{
    /** @param array<int, string> $ids */
    private function __construct(
        private readonly ?string $scalarId,
        private readonly array $ids,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray([
            'category_ids' => $request->input('category_ids'),
            'category_id' => $request->input('category_id'),
        ]);
    }

    /** @param array{category_id?: mixed, category_ids?: mixed} $filters */
    public static function fromArray(array $filters): self
    {
        $rawIds = $filters['category_ids'] ?? null;
        $ids = $rawIds === null || $rawIds === ''
            ? []
            : (is_array($rawIds) ? $rawIds : [$rawIds]);

        $scalarId = $filters['category_id'] ?? null;
        if (is_array($scalarId)) {
            $ids = array_merge($ids, $scalarId);
        } elseif ($scalarId !== null && $scalarId !== '') {
            $ids[] = $scalarId;
        }

        $validated = Validator::make(['category_ids' => $ids], [
            'category_ids' => ['array', 'max:50'],
            'category_ids.*' => ['uuid', 'distinct'],
        ])->validate();

        return new self(
            is_string($scalarId) && $scalarId !== '' ? $scalarId : null,
            array_values($validated['category_ids'] ?? []),
        );
    }

    /** @return array<int, string> */
    public function ids(): array
    {
        return $this->ids;
    }

    /** @return array{category_id: ?string, category_ids: array<int, string>} */
    public function toArray(): array
    {
        return [
            'category_id' => $this->scalarId,
            'category_ids' => $this->ids,
        ];
    }
}
