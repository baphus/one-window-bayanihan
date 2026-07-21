<?php

namespace Tests\Feature\Security;

use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseDocumentAuthorizationRemediationTest extends TestCase
{
    use RefreshDatabase;

    private function document(CaseFile $case, string $userId): CaseDocument
    {
        return CaseDocument::create([
            'file_name' => 'document.pdf',
            'file_path' => 'case-documents/'.$case->id.'/document.pdf',
            'file_type' => 'application/pdf',
            'case_id' => $case->id,
            'user_id' => $userId,
        ]);
    }

    public function test_any_case_manager_can_list_show_or_download(): void
    {
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $other = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['user_id' => $owner->id]);
        $document = $this->document($case, $owner->id);

        $this->actingAs($other)->getJson(route('cases.documents.index', $case->id))->assertOk();
        $this->actingAs($other)->getJson(route('cases.documents.show', [$case->id, $document->id]))->assertOk();

        $this->mock(StorageService::class, function ($mock): void {
            $mock->shouldReceive('temporaryUrl')->once()->andReturn('https://storage.test/document.pdf');
        });

        $this->actingAs($other)->get(route('cases.documents.download', [$case->id, $document->id]))->assertRedirect('https://storage.test/document.pdf');
    }

    public function test_document_json_never_contains_file_url(): void
    {
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['user_id' => $owner->id]);
        $document = $this->document($case, $owner->id);

        $index = $this->actingAs($owner)->getJson(route('cases.documents.index', $case->id));
        $index->assertOk()->assertJsonMissingPath('0.file_url');

        $show = $this->actingAs($owner)->getJson(route('cases.documents.show', [$case->id, $document->id]));
        $show->assertOk()->assertJsonMissingPath('file_url');
    }

    public function test_owner_and_admin_retain_authorized_download_redirects(): void
    {
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $case = CaseFile::factory()->create(['user_id' => $owner->id]);
        $document = $this->document($case, $owner->id);

        $this->mock(StorageService::class, function ($mock): void {
            $mock->shouldReceive('temporaryUrl')->twice()->andReturn('https://storage.test/document.pdf');
        });

        $this->actingAs($owner)
            ->get(route('cases.documents.download', [$case->id, $document->id]))
            ->assertRedirect('https://storage.test/document.pdf');
        $this->actingAs($admin)
            ->get(route('cases.documents.download', [$case->id, $document->id]))
            ->assertRedirect('https://storage.test/document.pdf');
    }
}
