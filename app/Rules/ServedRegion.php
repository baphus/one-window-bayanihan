<?php

namespace App\Rules;

use App\Services\PhilippineAddressService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a region the program does not currently serve.
 *
 * Accepts a served region either as its PSGC code — what the address dropdowns
 * submit, e.g. "0700000000" — or as any recognizable label for a served
 * region: the dataset display name ("Region VII (Central Visayas)"), the
 * parenthesized short form ("Central Visayas"), or the plain label
 * ("Region VII", "VII"). Everything derives from the configured region codes
 * and the address dataset, so widening the scope — or emptying the list to
 * mean "any region" — is a config change, not a code change.
 *
 * @see config/addresses.php for the served_regions list.
 */
final class ServedRegion implements ValidationRule
{
    public function __construct(
        private readonly PhilippineAddressService $addresses = new PhilippineAddressService
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // "Not a string" / "blank" is other rules' business (string, required).
        if (! is_string($value) || blank($value)) {
            return;
        }

        $servedRegions = config('addresses.served_regions', []);

        // An empty list disables the restriction entirely.
        if ($servedRegions === []) {
            return;
        }

        // Already a served PSGC code.
        if (in_array($value, $servedRegions, true)) {
            return;
        }

        // ponytail: the name forms (dataset display name, "Central Visayas",
        // "Region VII", "VII") stay accepted here because stored drafts and
        // legacy rows resubmit names; tightening to codes only would break
        // them. Plain labels come out of resolveAddressToCodes now, so the
        // former regionLabels() branch is gone with it.
        $resolved = $this->addresses->resolveAddressToCodes(['region' => $value]);
        if (is_string($resolved['region']) && in_array($resolved['region'], $servedRegions, true)) {
            return;
        }

        $fail('The selected region is not currently served. Choose a region from the list to continue.');
    }
}
