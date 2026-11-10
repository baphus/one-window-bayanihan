<?php

namespace Tests;

use App\Models\User;
use App\Services\CloudinaryAvatarService;
use App\Services\MfaPendingState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /** Explicit session fixture for HTTP tests exercising an authenticated, enrolled user. */
    protected function actingAsWithMfa(User $user): static
    {
        if ($user->mfa_enabled_at === null) {
            $user->forceFill([
                'mfa_secret' => 'JBSWY3DPEHPK3PXP',
                'mfa_recovery_codes' => [],
                'mfa_enabled_at' => now(),
            ])->saveQuietly();
        }

        $this->actingAs($user);
        $this->withSession([
            MfaPendingState::MARKER_KEY => [
                'user_id' => (string) $user->getKey(),
                'credential_fingerprint' => hash('sha256', (string) $user->password),
            ],
        ]);

        return $this;
    }

    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = config('database.default');
        $settings = config("database.connections.{$connection}", []);

        if (config('app.env') !== 'testing'
            || $connection !== 'pgsql'
            || ($settings['database'] ?? null) !== 'bayanihan_test'
            || ! empty($settings['url'])) {
            throw new \RuntimeException('Refusing PHPUnit run: effective database must be pgsql/bayanihan_test with no DB_URL.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->app->bind(CloudinaryAvatarService::class, fn () => new class extends CloudinaryAvatarService
        {
            public function __construct() {}

            public function uploadImage(UploadedFile $file, string $folder, string $publicId): string
            {
                return 'https://res.cloudinary.com/test/image/upload/'.Str::uuid().'/'.$folder.'/'.$publicId.'.jpg';
            }

            public function deleteByUrl(?string $url): void {}
        });
    }
}
