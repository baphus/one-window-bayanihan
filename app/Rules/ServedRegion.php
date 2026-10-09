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

        // Dataset display name or parenthesized short form ("Central Visayas").
        $resolved = $this->addresses->resolveAddressToCodes(['region' => $value]);
        if (is_string($resolved['region']) && in_array($resolved['region'], $servedRegions, true)) {
            return;
        }

        // Plain labels ("Region VII", "VII") derived from the served regions'
        // own display names, so no region is hardcoded here.
        foreach ($this->regionLabels($servedRegions) as $label) {
            if (strcasecmp($label, trim($value)) === 0) {
                return;
            }
        }

        $fail('The selected region is not currently served. Choose a region from the list to continue.');
    }

    /**
     * Recognizable plain labels for the served region codes, e.g.
     * "Region VII (Central Visayas)", "Region VII", "VII", "Central Visayas".
     *
     * @param  list<string>  $servedRegions
     * @return list<string>
     */
    private function regionLabels(array $servedRegions): array
    {
        $labels = [];

        foreach ($this->addresses->getRegions() as $region) {
            if (! in_array($region['code'], $servedRegions, true)) {
                continue;
            }

            $name = $region['name'];
            $labels[] = $name;

            if (preg_match('/^Region\s+(.+?)\s*\((.+)\)$/i', $name, $matches)) {
                $labels[] = 'Region '.$matches[1];
                $labels[] = $matches[1];
                $labels[] = $matches[2];
            }
        }

        return $labels;
    }
}
