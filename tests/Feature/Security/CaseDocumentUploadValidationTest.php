<?php

namespace Tests\Feature\Security;

use App\DTOs\FileStoreResult;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\IntakeDocumentService;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CaseDocumentUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_pdf_with_valid_case_id_returns_201(): void
    {
        $user = User::factory()->create();
        $caseFile = CaseFile::factory()->create(['user_id' => $user->id]);

        $this->actingAsWithMfa($user);

        $storage = $this->createMock(StorageService::class);
        $storage->method('validate')->willReturn([]);
        $storage->method('store')->willReturn(new FileStoreResult(
            path: 'case-documents/test/document.pdf',
            originalName: 'document.pdf',
            storedName: 'uuid-document.pdf',
            type: 'application/pdf',
            size: 1024,
            success: true,
        ));
        $storage->method('temporaryUrl')->willReturn(null);
        $this->app->instance(StorageService::class, $storage);

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson(route('cases.documents.store', $caseFile->id), [
            'file' => $file,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'file_name', 'file_path', 'file_type', 'size']);
    }

    public function test_database_failure_after_upload_deletes_object_and_rolls_back_document(): void
    {
        $user = User::factory()->create();
        $caseFile = CaseFile::factory()->create(['user_id' => $user->id]);
        $this->actingAsWithMfa($user);

        $storage = $this->createMock(StorageService::class);
        $storage->method('validate')->willReturn([]);
        $storage->method('store')->willReturn(new FileStoreResult(
            path: 'case-documents/test/rollback.pdf', originalName: 'rollback.pdf', storedName: 'rollback.pdf',
            type: 'application/pdf', size: 10, success: true,
        ));
        $storage->expects($this->once())->method('delete')->with('case-documents/test/rollback.pdf')->willReturn(true);
        $this->app->instance(StorageService::class, $storage);
        $this->mock(IntakeDocumentService::class)
            ->shouldReceive('syncRequirements')->once()->andThrow(new \RuntimeException('sync failed'));

        $this->postJson(route('cases.documents.store', $caseFile->id), [
            'file' => UploadedFile::fake()->create('rollback.pdf', 10, 'application/pdf'),
        ])->assertInternalServerError();

        $this->assertSame(0, CaseDocument::query()->where('case_id', $caseFile->id)->count());
    }

    public function test_invalid_file_type_returns_422(): void
    {
        $user = User::factory()->create();
        $caseFile = CaseFile::factory()->create(['user_id' => $user->id]);

        $this->actingAsWithMfa($user);

        $file = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');

        $response = $this->postJson(route('cases.documents.store', $caseFile->id), [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }
}
