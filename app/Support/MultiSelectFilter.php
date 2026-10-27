<?php

namespace App\Support;

use Closure;

/**
 * Normalises and validates multi-select filter parameters.
 *
 * The index pages send filters as arrays (`?status[]=OPEN&status[]=CLOSED`) so
 * a user can tick several values in one group. Existing links, bookmarks and
 * tests still send the legacy scalar form (`?status=OPEN`), so every read site
 * accepts either shape and treats it as a list.
 *
 * Use ids() in the query layer and rule() in the controller. The two exist
 * together so a value can never pass validation and then be unreadable by the
 * query, or vice versa.
 */
final class MultiSelectFilter
{
    /**
     * Coerce a request value into a de-duplicated, non-empty list of strings.
     *
     * Accepts a scalar (`'OPEN'`), an array of scalars (`['OPEN','CLOSED']`),
     * or null/absent. Non-scalar members, empty strings and whitespace-only
     * values are dropped rather than treated as a filter for "".
     *
     * @return array<int, string>
     */
    public static function ids(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $values = is_array($raw) ? $raw : [$raw];

        $clean = [];
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $clean[$value] = true;
        }

        return array_keys($clean);
    }

    /**
     * Validation rule for an array-or-scalar parameter restricted to $allowed.
     *
     * A closure rule is used (rather than `array` + `Rule::in`) because the
     * parameter may legitimately arrive as either a list or a single value.
     * The error is reported against the plain attribute name — `status`, not
     * `status.0` — so `$request->validate()` surfaces the same session error
     * key a scalar-only filter always used.
     *
     * @param  array<int, string>  $allowed
     * @return array<int, string|Closure>
     */
    public static function rule(array $allowed): array
    {
        return [
            'nullable',
            function (string $attribute, mixed $value, Closure $fail) use ($allowed): void {
                foreach (self::ids($value) as $candidate) {
                    if (! in_array($candidate, $allowed, true)) {
                        $fail("The selected {$attribute} is invalid.");

                        return;
                    }
                }
            },
        ];
    }
}
