<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Services\PhilippineAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers PSGC address resolution used by case-owned intake and the historical
 * backfill's resolver behavior without depending on legacy address tables.
 */
class AddressBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_maps_known_psgc_codes_to_names(): void
    {
        $resolver = app(PhilippineAddressService::class);

        $this->assertSame('Region VII (Central Visayas)', $resolver->resolve('0700000000'));
    }

    public function test_resolver_returns_the_input_for_an_unknown_code(): void
    {
        $resolver = app(PhilippineAddressService::class);

        $this->assertSame('9999999999', $resolver->resolve('9999999999'));
    }

    public function test_backfill_replaces_case_address_codes_and_leaves_names_alone(): void
    {
        $codedCase = $this->caseWithAddress(['address_region' => '0700000000']);
        $namedCase = $this->caseWithAddress(['address_region' => 'Region VII (Central Visayas)']);

        $this->runBackfill($codedCase, $namedCase);

        $this->assertSame('Region VII (Central Visayas)', $codedCase->fresh()->address_region);
        $this->assertSame('Region VII (Central Visayas)', $namedCase->fresh()->address_region);
    }

    public function test_backfill_is_idempotent(): void
    {
        $case = $this->caseWithAddress(['address_region' => '0700000000']);

        $this->runBackfill($case);
        $afterFirst = $case->fresh()->address_region;
        $this->runBackfill($case);
        $afterSecond = $case->fresh()->address_region;

        $this->assertSame($afterFirst, $afterSecond, 'Re-running the backfill changed the value again.');
    }

    private function caseWithAddress(array $address): CaseFile
    {
        return CaseFile::factory()->withIntake()->create($address);
    }

    private function runBackfill(CaseFile ...$cases): void
    {
        $resolver = app(PhilippineAddressService::class);
        $probe = $resolver->resolve('0700000000');

        if ($probe === '0700000000' || $probe === '') {
            throw new \RuntimeException(
                'AddressNameResolver cannot resolve PSGC codes — '
                .resource_path('js/data/philippine-addresses.json')
                .' (or its .ts fallback) is missing or unreadable. Refusing to run: this migration would '
                .'report success while changing nothing. Ensure the image retains resources/js/data.'
            );
        }

        foreach ($cases as $case) {
            foreach (['address_region', 'address_province', 'address_city_municipality', 'address_barangay'] as $column) {
                $value = $case->{$column};

                if (is_string($value) && preg_match('/^[0-9]{9,10}$/', $value)) {
                    $resolved = $resolver->resolve($value);

                    if ($resolved !== '' && $resolved !== $value) {
                        $case->{$column} = $resolved;
                    }
                }
            }

            $case->save();
        }
    }
}
