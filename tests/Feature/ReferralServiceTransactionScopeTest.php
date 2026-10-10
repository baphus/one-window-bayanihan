<?php

namespace Tests\Feature;

use App\DTOs\FileStoreResult;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class ReferralServiceTransactionScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_object_storage_upload_runs_outside_any_database_transaction(): void
    {
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create([
            'user_id' => $caseManager->id,
            'status' => 'OPEN',
        ]);
        $agency = Agency::factory()->create();

        $observedLevels = [];
        $storage = Mockery::mock(StorageService::class);
        $storage->shouldReceive('store')
            ->andReturnUsing(function (UploadedFile $file, string $directory) use (&$observedLevels) {
                $observedLevels[] = DB::transactionLevel();

                return new FileStoreResult(
                    path: $directory.'/stored.pdf',
                    originalName: $file->getClientOriginalName(),
                    storedName: 'stored.pdf',
                    type: 'application/pdf',
                    size: 123,
                );
            });
        $storage->shouldReceive('delete')->andReturnTrue();

        // RefreshDatabase wraps each test in a transaction, so compare against
        // the ambient level: the upload must not add any nesting on top.
        $baselineLevel = DB::transactionLevel();

        $referral = app(ReferralService::class)->createReferralWithDocuments(
            [
                'case_id' => $case->id,
                'agcy_id' => $agency->id,
                'notes' => null,
            ],
            $caseManager->id,
            [UploadedFile::fake()->create('document.pdf', 1)],
            $storage,
        );

        $this->assertSame([$baselineLevel], $observedLevels, 'Storage upload must not run inside a DB transaction.');
        $this->assertDatabaseHas('case_documents', [
            'referral_id' => $referral->id,
            'file_path' => 'case-documents/'.$case->id.'/stored.pdf',
        ]);
    }
}
