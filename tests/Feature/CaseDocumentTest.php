<?php

namespace Tests\Feature;

use App\DTOs\FileStoreResult;
use App\Models\Agency;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaseDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $caseManager;

    private CaseFile $case;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $this->case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'client_type' => 'OFW',
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'status' => 'OPEN',
            'user_id' => $this->caseManager->id,
        ]);
    }

    private function createDocument(): CaseDocument
    {
        return CaseDocument::create([
            'id' => fake()->uuid(),
            'file_name' => 'test-doc.pdf',
            'file_path' => '/fake/path/doc.pdf',
            'file_type' => 'application/pdf',
            'case_id' => $this->case->id,
            'user_id' => $this->caseManager->id,
        ]);
    }

    public function test_case_creator_can_list_documents()
    {
        $this->createDocument();

        $response = $this->actingAs($this->caseManager)
            ->getJson(route('cases.documents.index', $this->case->id));

        $response->assertOk()
            ->assertJsonCount(1);
    }

    public function test_unauthorized_user_cannot_list_documents()
    {
        $this->createDocument();

        $unauthorized = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => null,
        ]);

        $response = $this->actingAs($unauthorized)
            ->getJson(route('cases.documents.index', $this->case->id));

        $response->assertForbidden();
    }

    public function test_admin_can_list_documents()
    {
        $this->createDocument();

        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)
            ->getJson(route('cases.documents.index', $this->case->id));

        $response->assertOk()
            ->assertJsonCount(1);
    }

    public function test_case_manager_can_list_documents()
    {
        $this->createDocument();

        $otherManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($otherManager)
            ->getJson(route('cases.documents.index', $this->case->id));

        $response->assertOk()
            ->assertJsonCount(1);
    }

    public function test_document_soft_delete()
    {
        $doc = $this->createDocument();

        $response = $this->actingAs($this->caseManager)
            ->deleteJson(route('cases.documents.destroy', [$this->case->id, $doc->id]));

        $response->assertOk()
            ->assertJson(['message' => 'Document deleted successfully.']);

        $this->assertDatabaseHas('case_documents', [
            'id' => $doc->id,
            'is_deleted' => 1,
        ]);
    }

    public function test_deleted_document_not_listed()
    {
        $doc = $this->createDocument();
        $doc->update(['is_deleted' => true, 'deleted_at' => now(), 'deleted_by' => $this->caseManager->id]);

        $response = $this->actingAs($this->caseManager)
            ->getJson(route('cases.documents.index', $this->case->id));

        $response->assertOk()
            ->assertJsonCount(0);
    }

    public function test_agency_user_with_active_referral_can_access()
    {
        $this->createDocument();

        $agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);

        Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $agency->id,
        ]);

        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($agencyUser)
            ->getJson(route('cases.documents.index', $this->case->id));

        $response->assertOk()
            ->assertJsonCount(1);
    }

    public function test_agency_user_without_active_referral_cannot_access()
    {
        $this->createDocument();

        $agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);

        Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'COMPLETED',
            'case_id' => $this->case->id,
            'agcy_id' => $agency->id,
        ]);

        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($agencyUser)
            ->getJson(route('cases.documents.index', $this->case->id));

        $response->assertForbidden();
    }

    public function test_document_creation_stores_size()
    {
        Storage::fake('supabase');

        // Mock StorageService to bypass deep MIME inspection (empty-content
        // fake uploads are detected as application/x-empty)
        $this->mock(StorageService::class, function ($mock) {
            $mock->shouldReceive('validate')->andReturn([]);
            $mock->shouldReceive('store')->andReturn(new FileStoreResult(
                path: 'case-documents/test/document.pdf',
                originalName: 'document.pdf',
                storedName: 'uuid-document.pdf',
                type: 'application/pdf',
                size: 2048,
                success: true,
            ));
        });

        $file = UploadedFile::fake()->create('document.pdf', 2048);

        $response = $this->actingAs($this->caseManager)
            ->postJson(route('cases.documents.store', $this->case->id), [
                'file' => $file,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('case_documents', [
            'case_id' => $this->case->id,
            'file_name' => 'document.pdf',
        ]);

        $document = CaseDocument::where('case_id', $this->case->id)->first();
        $this->assertNotNull($document->size);
        $this->assertGreaterThan(0, $document->size);
    }

    public function test_invalid_file_type_returns_422()
    {
        $file = UploadedFile::fake()->create('malicious.exe', 100);

        $response = $this->actingAs($this->caseManager)
            ->postJson(route('cases.documents.store', $this->case->id), [
                'file' => $file,
            ]);

        $response->assertUnprocessable();
    }

    public function test_unauthorized_user_cannot_upload()
    {
        $unauthorized = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => null,
        ]);

        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $response = $this->actingAs($unauthorized)
            ->postJson(route('cases.documents.store', $this->case->id), [
                'file' => $file,
            ]);

        $response->assertForbidden();
    }
}
