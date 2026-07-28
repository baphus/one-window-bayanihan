<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Services\CaseNumberGenerator;
use App\Services\CaseService;
use App\Services\IntakeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the canonical identifier formats.
 *
 * Three different case-number formats were in circulation at once: the real one
 * produced by CaseService and IntakeService, a look-alike CM-YYYYMMDD-NNNN
 * invented and displayed by the case-creation wizard and then discarded, and a
 * CASE-YYYYMMDD-XXXX documented to users in four helpdesk articles. A case
 * manager reading any of the wrong ones would give a client an identifier that
 * does not exist.
 *
 * The canonical format is now OWB-{YEAR}{MONTH}-{NNNNN}, counting per month.
 * Cases issued under the earlier per-year OWB-{YEAR}-{NNNNN} series keep their
 * numbers and cannot collide with it — four digits in the period segment there,
 * six here — so this pins only what new allocation produces.
 */
class CaseNumberFormatTest extends TestCase
{
    use RefreshDatabase;

    // OWB-{YEAR}{MONTH}-{NNNNN}, e.g. OWB-202607-00001. Six digits in the period
    // segment, not four: the series counts per month.
    private const CASE_NUMBER_PATTERN = '/^OWB-\d{6}-\d{5}$/';

    // Crockford base32: digits plus uppercase letters excluding I, L, O and U,
    // so nothing is ambiguous when a client reads a tracker aloud or copies it
    // from an SMS. Ten characters give 32^10, about 50 bits.
    private const TRACKER_PATTERN = '/^OWBAP-[0-9A-HJKMNP-TV-Z]{10}$/';

    public function test_factory_produces_canonical_identifiers(): void
    {
        $case = CaseFile::factory()->create();

        $this->assertMatchesRegularExpression(self::CASE_NUMBER_PATTERN, $case->case_number);
        $this->assertMatchesRegularExpression(self::TRACKER_PATTERN, $case->tracker_number);
    }

    public function test_case_number_period_follows_the_operating_timezone_not_utc(): void
    {
        // 31 December 2026, 17:00 UTC is already 1 January 2027 in Manila
        // (UTC+8). Under a UTC-derived period this produces OWB-202612-..., so a
        // case filed on New Year's Day in Manila carries the previous month and
        // the previous year in its reference.
        $this->travelTo(CarbonImmutable::parse('2026-12-31 17:00:00', 'UTC'));

        $this->assertSame(
            '202701',
            now()->timezone(config('app.operating_timezone'))->format('Ym'),
            'Sanity check on the fixture: Manila should already be in January 2027.'
        );

        foreach ([CaseService::class, IntakeService::class] as $class) {
            $reflection = new \ReflectionMethod(app($class), 'generateCaseNumber');
            $reflection->setAccessible(true);

            $this->assertStringStartsWith(
                'OWB-202701-',
                $reflection->invoke(app($class)),
                $class.' derived the period from UTC rather than the operating timezone.'
            );
        }

        $this->travelBack();
    }

    public function test_a_month_boundary_starts_a_new_series_and_cannot_repeat_an_old_number(): void
    {
        // Per-month numbering makes the timezone boundary twelve times as
        // frequent as it was, so the rollover is worth pinning directly.
        $this->travelTo(CarbonImmutable::parse('2026-07-31 15:00:00', 'UTC')); // 23:00 in Manila
        $july = app(CaseNumberGenerator::class)->nextCaseNumber();

        $this->travelTo(CarbonImmutable::parse('2026-07-31 16:00:00', 'UTC')); // 00:00 on 1 Aug in Manila
        $august = app(CaseNumberGenerator::class)->nextCaseNumber();

        $this->assertSame('OWB-202607-00001', $july);
        $this->assertSame('OWB-202608-00001', $august);
        $this->assertNotSame($july, $august);

        $this->travelBack();
    }

    public function test_both_generators_agree_on_the_identifier_formats(): void
    {
        // CaseService and IntakeService each generate identifiers independently.
        // They had already diverged once: IntakeService returned
        // strtoupper(bin2hex(random_bytes(4))) — eight hex characters with no
        // OWBAP- prefix — so every self-filed OFW received a tracker the
        // tracking portal and helpdesk article told them to type differently,
        // and TrackingService matches tracker_number exactly. Only CaseService
        // had a format assertion, which is why the divergence shipped.
        $caseService = app(CaseService::class);
        $intakeService = app(IntakeService::class);

        foreach (['case_number' => self::CASE_NUMBER_PATTERN, 'tracker' => self::TRACKER_PATTERN] as $label => $pattern) {
            foreach (['CaseService' => $caseService, 'IntakeService' => $intakeService] as $name => $service) {
                $method = $label === 'case_number' ? 'generateCaseNumber' : 'generateTrackerNumber';

                $reflection = new \ReflectionMethod($service, $method);
                $reflection->setAccessible(true);
                $value = $reflection->invoke($service);

                $this->assertMatchesRegularExpression(
                    $pattern,
                    $value,
                    "{$name}::{$method}() produced '{$value}', which does not match the canonical format."
                );
            }
        }
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
            .'The only supported format is OWB-{YEAR}{MONTH}-{NNNNN}.'
        );
    }
}
