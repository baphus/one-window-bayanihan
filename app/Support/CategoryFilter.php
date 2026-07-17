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
        $rawIds = $request->input('category_ids');
        $ids = $rawIds === null || $rawIds === ''
            ? []
            : (is_array($rawIds) ? $rawIds : [$rawIds]);

        if ($request->filled('category_id')) {
            $ids[] = $request->input('category_id');
        }

        $validated = Validator::make(['category_ids' => $ids], [
            'category_ids' => ['array', 'max:50'],
            'category_ids.*' => ['uuid', 'distinct'],
        ])->validate();

        return new self(
            $request->filled('category_id') ? $request->input('category_id') : null,
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
