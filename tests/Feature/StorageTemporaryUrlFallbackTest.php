<?php

namespace Tests\Feature;

use App\Services\StorageService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Local disks cannot mint a presigned URL the way object storage can, which
 * used to leave StorageService::temporaryUrl() returning null and turned every
 * download into a 404. These tests pin the fallback: the application signs an
 * expiring route for the disk and path, and TemporaryFileController streams the
 * bytes for anyone holding a valid, unexpired link.
 */
class StorageTemporaryUrlFallbackTest extends TestCase
{
    /**
     * A real local disk rooted in a throwaway directory — Storage::fake() would
     * not prove anything about the configured driver, and the controller
     * whitelists config, not the fake.
     */
    private const DISK = 'temp-url-fallback-test';

    private const PATH = 'case-documents/fallback-test.txt';

    private const CONTENTS = "One Window Bayanihan\nFallback stream bytes.\n";

    private string $diskRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->diskRoot = storage_path('framework/testing/disks/'.self::DISK);
        File::deleteDirectory($this->diskRoot);
        File::ensureDirectoryExists($this->diskRoot.'/case-documents');
        File::put($this->diskRoot.'/'.self::PATH, self::CONTENTS);

        Config::set('filesystems.disks.'.self::DISK, [
            'driver' => 'local',
            'root' => $this->diskRoot,
            'serve' => false,
            'throw' => false,
            'report' => false,
        ]);
        Config::set('filesystems.default', self::DISK);

        Storage::forgetDisk(self::DISK);
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk(self::DISK);
        File::deleteDirectory($this->diskRoot);
        parent::tearDown();
    }

    /**
     * Resolve a disk as the concrete adapter, so capability checks below are
     * asserted against the same object StorageService asks.
     */
    private function disk(string $name): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($name);

        return $disk;
    }

    // -----------------------------------------------
    //  A URL is produced at all
    // -----------------------------------------------

    public function test_local_disk_returns_an_application_signed_url_instead_of_null(): void
    {
        $this->assertFalse(
            $this->disk(self::DISK)->providesTemporaryUrls(),
            'Premise of this test: the local driver cannot mint temporary URLs.'
        );

        $url = app(StorageService::class)->temporaryUrl(self::PATH);

        $this->assertNotNull($url);
        $this->assertSame(
            '/files/temp/'.self::DISK.'/case-documents/fallback-test.txt',
            parse_url((string) $url, PHP_URL_PATH)
        );
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_private_disk_media_urls_are_signed_rather_than_null(): void
    {
        $this->assertFalse($this->disk('private')->providesTemporaryUrls());

        $url = app(StorageService::class)->temporaryUrlUntil(
            'avatars/user-test.jpg',
            now()->addMinutes(5),
            'private'
        );

        $this->assertNotNull($url, 'HasAvatar accessors resolve to null when this fails.');
        $this->assertStringContainsString('/files/temp/private/avatars/user-test.jpg?', $url);

        $query = [];
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('expires', $query);
        $this->assertGreaterThan(now()->timestamp, (int) $query['expires']);
        $this->assertLessThanOrEqual(now()->addMinutes(6)->timestamp, (int) $query['expires']);
    }

    public function test_unknown_disk_still_returns_null_rather_than_a_broken_link(): void
    {
        $this->assertNull(app(StorageService::class)->temporaryUrl(self::PATH, 24, 'no-such-disk'));
    }

    // -----------------------------------------------
    //  Signature enforcement
    // -----------------------------------------------

    public function test_request_without_a_signature_is_rejected(): void
    {
        $this->get('/files/temp/'.self::DISK.'/'.self::PATH)->assertForbidden();
    }

    public function test_expired_signature_is_rejected(): void
    {
        $url = app(StorageService::class)->temporaryUrlUntil(self::PATH, now()->subMinute(), self::DISK);

        $this->assertNotNull($url);
        $this->get($url)->assertForbidden();
    }

    public function test_tampered_path_is_rejected(): void
    {
        $url = app(StorageService::class)->temporaryUrl(self::PATH);

        $tampered = str_replace('fallback-test.txt', 'someone-elses-file.txt', (string) $url);

        $this->assertNotSame($url, $tampered);
        $this->get($tampered)->assertForbidden();
    }

    public function test_tampered_disk_is_rejected(): void
    {
        $url = app(StorageService::class)->temporaryUrl(self::PATH);

        $tampered = str_replace('/files/temp/'.self::DISK.'/', '/files/temp/private/', (string) $url);

        $this->assertNotSame($url, $tampered);
        $this->get($tampered)->assertForbidden();
    }

    // -----------------------------------------------
    //  Streaming
    // -----------------------------------------------

    public function test_valid_signature_streams_the_stored_bytes(): void
    {
        $response = $this->get(app(StorageService::class)->temporaryUrl(self::PATH));

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $response->assertHeader('Content-Length', (string) strlen(self::CONTENTS));
        // Directives, not order: Symfony re-orders Cache-Control on the way out.
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString(
            'fallback-test.txt',
            (string) $response->headers->get('Content-Disposition')
        );
        $this->assertSame(self::CONTENTS, $response->streamedContent());
    }

    public function test_signed_link_for_a_missing_file_is_a_404(): void
    {
        $url = app(StorageService::class)->temporaryUrl('case-documents/not-stored.txt');

        $this->assertNotNull($url);
        $this->get($url)->assertNotFound();
    }

    public function test_streaming_leaves_the_file_on_disk_for_the_next_link(): void
    {
        $this->get(app(StorageService::class)->temporaryUrl(self::PATH))->assertOk();

        $this->assertSame(self::CONTENTS, File::get($this->diskRoot.'/'.self::PATH));
    }
}
