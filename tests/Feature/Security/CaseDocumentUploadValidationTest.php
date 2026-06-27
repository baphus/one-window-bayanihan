<?php

namespace Tests\Feature\Security;

use App\DTOs\FileStoreResult;
use App\Models\CaseFile;
use App\Models\User;
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

        $this->actingAs($user);

        $this->mock(StorageService::class, function ($mock) {
            $mock->shouldReceive('validate')->andReturn([]);
            $mock->shouldReceive('store')->andReturn(new FileStoreResult(
                path: 'case-documents/test/document.pdf',
                originalName: 'document.pdf',
                storedName: 'uuid-document.pdf',
                type: 'application/pdf',
                size: 1024,
                success: true,
            ));
        });

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson(route('cases.documents.store', $caseFile->id), [
            'file' => $file,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'file_name', 'file_path', 'file_type', 'size']);
    }

    public function test_invalid_file_type_returns_422(): void
    {
        $user = User::factory()->create();
        $caseFile = CaseFile::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $file = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');

        $response = $this->postJson(route('cases.documents.store', $caseFile->id), [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }
}
