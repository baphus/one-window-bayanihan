<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the canonical identifier formats.
 *
 * Three different case-number formats were in circulation at once: the real
 * OWB-{YEAR}-{NNNNN} produced by CaseService and IntakeService, a look-alike
 * CM-YYYYMMDD-NNNN invented and displayed by the case-creation wizard and then
 * discarded, and a CASE-YYYYMMDD-XXXX documented to users in four helpdesk
 * articles. A case manager reading any of the wrong ones would give a client an
 * identifier that does not exist.
 */
class CaseNumberFormatTest extends TestCase
{
    use RefreshDatabase;

    private const CASE_NUMBER_PATTERN = '/^OWB-\d{4}-\d{5}$/';

    private const TRACKER_PATTERN = '/^OWBAP-[A-Z0-9]{7}$/';

    public function test_factory_produces_canonical_identifiers(): void
    {
        $case = CaseFile::factory()->create();

        $this->assertMatchesRegularExpression(self::CASE_NUMBER_PATTERN, $case->case_number);
        $this->assertMatchesRegularExpression(self::TRACKER_PATTERN, $case->tracker_number);
    }

    public function test_no_source_file_generates_a_competing_case_number_format(): void
    {
        // Guards against reintroducing a second generator. Only the canonical
        // OWB- prefix may be used to build a case number anywhere in the app,
        // frontend included.
        $roots = [base_path('app'), base_path('resources/js'), base_path('database')];
        $offenders = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($files as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                if (! in_array($file->getExtension(), ['php', 'js', 'jsx', 'ts', 'tsx'], true)) {
                    continue;
                }
                // Test fixtures may use arbitrary strings as sample data.
                if (str_contains($file->getPathname(), '__tests__')) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname()) ?: '';

                // A template that builds an identifier from a date plus digits
                // using any prefix other than OWB.
                if (preg_match('/[\'"`](?:CM|CASE)-(?:\$\{|\{|Y{4}|\d{8})/', $contents)) {
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($offenders)),
            'These files build or document a case number with a non-canonical prefix. '
            .'The only supported format is OWB-{YEAR}-{NNNNN}.'
        );
    }
}
