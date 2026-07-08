<?php

namespace Tests\Unit\Services;

use App\DTOs\FileStoreResult;
use App\Services\StorageService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageServiceTest extends TestCase
{
    private StorageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a dedicated test disk so we control what gets faked
        Config::set('filesystems.default', 'object-storage');

        $this->service = app(StorageService::class);
    }

    protected function tearDown(): void
    {
        // Clean up any Storage facade mocks to avoid test leakage
        if (property_exists(Storage::class, 'clearResolvedInstance')) {
            Storage::clearResolvedInstance();
        }
        parent::tearDown();
    }

    // -----------------------------------------------
    //  store()
    // -----------------------------------------------

    public function test_store_valid_file(): void
    {
        Storage::fake('object-storage');

        $file = UploadedFile::fake()->image('test-photo.jpg', 500);
        $result = $this->service->store($file, 'case-files');

        $this->assertInstanceOf(FileStoreResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertNotNull($result->path);
        $this->assertSame('test-photo.jpg', $result->originalName);
        $this->assertNull($result->error);

        // Verify the file was actually stored on the fake disk
        Storage::disk('object-storage')->assertExists($result->path);
    }

    public function test_store_sanitizes_filename(): void
    {
        Storage::fake('object-storage');

        $file = UploadedFile::fake()->create('../../etc/passwd.pdf', 100);
        $result = $this->service->store($file, 'files');

        $this->assertTrue($result->success);

        // The stored filename (UUID-based) must not contain path-traversal characters
        $this->assertStringNotContainsString('..', $result->storedName);
        $this->assertStringNotContainsString('/', $result->storedName);

        // Verify the stored file path is safe
        Storage::disk('object-storage')->assertExists($result->path);
    }

    public function test_store_preserves_original_name(): void
    {
        Storage::fake('object-storage');

        $originalName = 'my-document.pdf';
        $file = UploadedFile::fake()->create($originalName, 100);
        $result = $this->service->store($file, 'documents');

        $this->assertSame($originalName, $result->originalName);
    }

    // -----------------------------------------------
    //  delete()
    // -----------------------------------------------

    public function test_delete_returns_true(): void
    {
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->with('case-files/test.pdf')->once()->andReturn(true);

        Storage::shouldReceive('disk')->with('object-storage')->once()->andReturn($disk);

        $result = $this->service->delete('case-files/test.pdf');

        $this->assertTrue($result);
    }

    public function test_delete_returns_false_for_missing(): void
    {
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->with('non-existent/file.pdf')->once()->andReturn(false);

        Storage::shouldReceive('disk')->with('object-storage')->once()->andReturn($disk);

        $result = $this->service->delete('non-existent/file.pdf');

        $this->assertFalse($result);
    }

    public function test_delete_does_not_throw(): void
    {
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->with('some/path')->once()->andThrow(new \RuntimeException('Disk failure'));

        Storage::shouldReceive('disk')->with('object-storage')->once()->andReturn($disk);

        $result = $this->service->delete('some/path');

        $this->assertFalse($result);
    }

    // -----------------------------------------------
    //  temporaryUrl()
    // -----------------------------------------------

    public function test_temporary_url(): void
    {
        Storage::shouldReceive('disk->temporaryUrl')
            ->once()
            ->andReturn('https://fake-bucket.supabase.co/storage/v1/object/signed/case-files/test.pdf?token=abc');

        $url = $this->service->temporaryUrl('case-files/test.pdf', 24);

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://', $url);
        $this->assertStringContainsString('token=', $url);
    }

    // -----------------------------------------------
    //  validate()
    // -----------------------------------------------

    public function test_validate_returns_empty_for_valid(): void
    {
        // A valid image file: jpeg extension is allowed, 100 KB < 10240 KB limit
        $file = UploadedFile::fake()->image('avatar.jpg', 100);

        $errors = $this->service->validate($file, 'default');

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    public function test_validate_returns_error_for_oversized(): void
    {
        // Override max_size to 500 KB so we can reliably exceed it
        Config::set('file-uploads.default.max_size', 500);

        // 600 KB file → exceeds 500 KB limit
        $file = UploadedFile::fake()->create('large.pdf', 600);

        $errors = $this->service->validate($file, 'default');

        $this->assertNotEmpty($errors);

        $found = false;
        foreach ($errors as $error) {
            if (str_contains($error, 'exceeds maximum allowed size')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected a file-size error message.');
    }

    public function test_validate_returns_error_for_disallowed_mime(): void
    {
        // .exe files are not in the allowed mimes list
        $file = UploadedFile::fake()->create('malware.exe', 100);

        $errors = $this->service->validate($file, 'default');

        $this->assertNotEmpty($errors);

        $found = false;
        foreach ($errors as $error) {
            if (str_contains($error, 'not allowed')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected a type-not-allowed error message.');
    }

    public function test_validate_returns_error_for_unknown_context(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $errors = $this->service->validate($file, 'non_existent_context');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('No validation configuration found', $errors[0]);
    }
}
