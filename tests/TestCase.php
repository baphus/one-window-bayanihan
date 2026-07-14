<?php

namespace Tests;

use App\Services\CloudinaryAvatarService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(CloudinaryAvatarService::class, fn () => new class extends CloudinaryAvatarService
        {
            public function uploadImage(UploadedFile $file, string $folder, string $publicId): string
            {
                return 'https://res.cloudinary.com/test/image/upload/'.Str::uuid().'/'.$folder.'/'.$publicId.'.jpg';
            }

            public function deleteByUrl(?string $url): void {}
        });
    }
}
