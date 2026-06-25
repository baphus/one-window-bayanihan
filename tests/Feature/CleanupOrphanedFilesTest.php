<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class CleanupOrphanedFilesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CaseFile $case;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $this->case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'client_type' => 'OFW',
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'status' => 'OPEN',
            'user_id' => $this->user->id,
        ]);
    }

    protected function tearDown(): void
    {
        $this->app->forgetInstance(StorageService::class);
        parent::tearDown();
    }

    public function test_command_deletes_old_soft_deleted(): void
    {
        CaseDocument::create([
            'id' => fake()->uuid(),
            'file_name' => 'old.pdf',
            'file_path' => 'case-files/old.pdf',
            'file_type' => 'application/pdf',
            'case_id' => $this->case->id,
            'user_id' => $this->user->id,
            'is_deleted' => true,
            'deleted_at' => now()->subDays(2),
        ]);

        $mock = Mockery::mock(StorageService::class);
        $mock->shouldReceive('delete')->once()->with('case-files/old.pdf')->andReturn(true);
        $this->instance(StorageService::class, $mock);

        $exitCode = Artisan::call('storage:cleanup-orphans');

        $this->assertEquals(0, $exitCode);
    }

    public function test_command_skips_recently_deleted(): void
    {
        CaseDocument::create([
            'id' => fake()->uuid(),
            'file_name' => 'recent.pdf',
            'file_path' => 'case-files/recent.pdf',
            'file_type' => 'application/pdf',
            'case_id' => $this->case->id,
            'user_id' => $this->user->id,
            'is_deleted' => true,
            'deleted_at' => now()->subHour(),
        ]);

        $mock = Mockery::mock(StorageService::class);
        $mock->shouldNotReceive('delete');
        $this->instance(StorageService::class, $mock);

        $exitCode = Artisan::call('storage:cleanup-orphans');

        $this->assertEquals(0, $exitCode);
    }

    public function test_command_dry_run_does_not_delete(): void
    {
        CaseDocument::create([
            'id' => fake()->uuid(),
            'file_name' => 'dry-run.pdf',
            'file_path' => 'case-files/dry-run.pdf',
            'file_type' => 'application/pdf',
            'case_id' => $this->case->id,
            'user_id' => $this->user->id,
            'is_deleted' => true,
            'deleted_at' => now()->subDays(2),
        ]);

        $mock = Mockery::mock(StorageService::class);
        $mock->shouldNotReceive('delete');
        $this->instance(StorageService::class, $mock);

        $exitCode = Artisan::call('storage:cleanup-orphans', ['--dry-run' => true]);

        $this->assertEquals(0, $exitCode);
    }

    public function test_command_skips_archived_attachments(): void
    {
        $agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);

        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $agency->id,
        ]);

        ReferralAttachment::create([
            'id' => fake()->uuid(),
            'referral_id' => $referral->id,
            'file_name' => 'archived.pdf',
            'file_path' => 'attachments/archived.pdf',
            'file_type' => 'application/pdf',
            'user_id' => $this->user->id,
            'is_deleted' => true,
            'is_archived' => true,
            'deleted_at' => now()->subDays(2),
        ]);

        $mock = Mockery::mock(StorageService::class);
        $mock->shouldNotReceive('delete');
        $this->instance(StorageService::class, $mock);

        $exitCode = Artisan::call('storage:cleanup-orphans');

        $this->assertEquals(0, $exitCode);
    }

    public function test_command_skips_non_deleted(): void
    {
        CaseDocument::create([
            'id' => fake()->uuid(),
            'file_name' => 'active.pdf',
            'file_path' => 'case-files/active.pdf',
            'file_type' => 'application/pdf',
            'case_id' => $this->case->id,
            'user_id' => $this->user->id,
            'is_deleted' => false,
        ]);

        $mock = Mockery::mock(StorageService::class);
        $mock->shouldNotReceive('delete');
        $this->instance(StorageService::class, $mock);

        $exitCode = Artisan::call('storage:cleanup-orphans');

        $this->assertEquals(0, $exitCode);
    }
}
