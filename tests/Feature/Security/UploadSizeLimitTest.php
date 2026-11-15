<?php

namespace Tests\Feature\Security;

use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadSizeLimitTest extends TestCase
{
    public function test_storage_service_accepts_file_at_five_mib_and_rejects_larger_file(): void
    {
        config(['filesystems.default' => 'local', 'file-uploads.default.mimes' => []]);
        $this->assertSame(5120, config('file-uploads.max_size'));

        $atLimit = UploadedFile::fake()->createWithContent('limit.pdf', str_repeat('a', 5 * 1024 * 1024));
        $tooLarge = UploadedFile::fake()->createWithContent('large.pdf', str_repeat('a', 5 * 1024 * 1024 + 1));
        $storage = $this->app->make(StorageService::class);

        $this->assertEmpty($storage->validate($atLimit));
        $this->assertNotEmpty($storage->validate($tooLarge));

        $result = $storage->store($tooLarge, 'uploads');
        $this->assertFalse($result->success);
    }
}
