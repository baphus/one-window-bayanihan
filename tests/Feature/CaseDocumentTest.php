<?php

namespace Tests\Feature;

use App\DTOs\FileStoreResult;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CaseDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $caseManager;

    private CaseFile $case;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

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
            'role' => UserRole::AGENCY->value,
            'agcy_id' => null,
        ]);

        $response = $this->actingAs($unauthorized)
            ->getJson(route('cases.documents.index', $this->case->id));

        $response->assertForbidden();
    }

    public function test_admin_can_list_documents()
    {
        $this->createDocument();

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($admin)
            ->getJson(route('cases.documents.index', $this->case->id));

        $response->assertOk()
            ->assertJsonCount(1);
    }

    public function test_any_case_manager_can_list_show_or_download_documents()
    {
        $document = $this->createDocument();

        $otherManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAs($otherManager)
            ->getJson(route('cases.documents.index', $this->case->id))
            ->assertOk()
            ->assertJsonCount(1);

        $this->actingAs($otherManager)
            ->getJson(route('cases.documents.show', [$this->case->id, $document->id]))
            ->assertOk();

        $storage = $this->createMock(StorageService::class);
        $storage->expects($this->once())->method('temporaryUrl')->willReturn('https://storage.test/document.pdf');
        $this->app->instance(StorageService::class, $storage);

        $this->actingAs($otherManager)
            ->get(route('cases.documents.download', [$this->case->id, $document->id]))
            ->assertRedirect('https://storage.test/document.pdf');
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

    public function test_admin_can_delete_document()
    {
        $doc = $this->createDocument();

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($admin)
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

        $this->createDocument()->update(['referral_id' => $referral->id]);

        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($agencyUser)
            ->getJson(route('cases.documents.index', $this->case->id));

        $response->assertOk()
            ->assertJsonCount(1);
    }

    public function test_agency_user_with_active_referral_cannot_upload()
    {
        $agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency-upload',
        ]);

        Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $agency->id,
        ]);

        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);

        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $response = $this->actingAs($agencyUser)
            ->postJson(route('cases.documents.store', $this->case->id), [
                'file' => $file,
            ]);

        $response->assertForbidden();
    }

    public function test_agency_user_with_active_referral_cannot_delete()
    {
        $doc = $this->createDocument();

        $agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency-delete',
        ]);

        Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $agency->id,
        ]);

        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($agencyUser)
            ->deleteJson(route('cases.documents.destroy', [$this->case->id, $doc->id]));

        $response->assertForbidden();

        $this->assertDatabaseHas('case_documents', [
            'id' => $doc->id,
            'is_deleted' => 0,
        ]);
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
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($agencyUser)
            ->getJson(route('cases.documents.index', $this->case->id));

        $response->assertForbidden();
    }

    public function test_agency_cannot_list_show_or_download_another_agencys_referral_document(): void
    {
        $ownerAgency = Agency::create(['id' => fake()->uuid(), 'name' => 'Owner', 'short' => 'OW', 'slug' => 'owner-docs']);
        $otherAgency = Agency::create(['id' => fake()->uuid(), 'name' => 'Other', 'short' => 'OT', 'slug' => 'other-docs']);
        $referral = Referral::create([
            'id' => fake()->uuid(), 'required_services' => 'Service', 'status' => 'PENDING',
            'case_id' => $this->case->id, 'agcy_id' => $ownerAgency->id,
        ]);
        Referral::create([
            'id' => fake()->uuid(), 'required_services' => 'Other service', 'status' => 'PENDING',
            'case_id' => $this->case->id, 'agcy_id' => $otherAgency->id,
        ]);
        $document = $this->createDocument();
        $document->update(['referral_id' => $referral->id]);
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $otherAgency->id]);

        $this->actingAs($agencyUser)->getJson(route('cases.documents.index', $this->case->id))
            ->assertOk()->assertJsonCount(0);
        $this->actingAs($agencyUser)->getJson(route('cases.documents.show', [$this->case->id, $document->id]))
            ->assertForbidden();
        $this->actingAs($agencyUser)->get(route('cases.documents.download', [$this->case->id, $document->id]))
            ->assertForbidden();
    }

    public function test_agency_sees_general_case_and_own_referral_documents_only(): void
    {
        $ownAgency = Agency::create(['id' => fake()->uuid(), 'name' => 'Own', 'short' => 'OW', 'slug' => 'own-visibility']);
        $otherAgency = Agency::create(['id' => fake()->uuid(), 'name' => 'Other', 'short' => 'OT', 'slug' => 'other-visibility']);
        $ownReferral = Referral::create([
            'id' => fake()->uuid(), 'required_services' => 'Service', 'status' => 'PENDING',
            'case_id' => $this->case->id, 'agcy_id' => $ownAgency->id,
        ]);
        $otherReferral = Referral::create([
            'id' => fake()->uuid(), 'required_services' => 'Other service', 'status' => 'PENDING',
            'case_id' => $this->case->id, 'agcy_id' => $otherAgency->id,
        ]);

        // (b) General case file — no referral link.
        $generalDoc = $this->createDocument();
        // (a) Manager-uploaded document on the agency's own referral.
        $ownReferralDoc = $this->createDocument();
        $ownReferralDoc->update(['referral_id' => $ownReferral->id]);
        // Another agency's referral document — must stay invisible.
        $otherReferralDoc = $this->createDocument();
        $otherReferralDoc->update(['referral_id' => $otherReferral->id]);

        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $ownAgency->id]);

        // Index returns general case files + own-referral docs, never the other agency's.
        $response = $this->actingAs($agencyUser)->getJson(route('cases.documents.index', $this->case->id));
        $response->assertOk()->assertJsonCount(2);
        $listedIds = collect($response->json())->pluck('id');
        $this->assertContains($generalDoc->id, $listedIds);
        $this->assertContains($ownReferralDoc->id, $listedIds);
        $this->assertNotContains($otherReferralDoc->id, $listedIds);

        // Direct download: general case file and own-referral doc are allowed.
        $storage = $this->createMock(StorageService::class);
        $storage->expects($this->exactly(2))->method('temporaryUrl')->willReturn('https://storage.test/document.pdf');
        $this->app->instance(StorageService::class, $storage);

        $this->actingAs($agencyUser)
            ->get(route('cases.documents.download', [$this->case->id, $generalDoc->id]))
            ->assertRedirect('https://storage.test/document.pdf');
        $this->actingAs($agencyUser)
            ->get(route('cases.documents.download', [$this->case->id, $ownReferralDoc->id]))
            ->assertRedirect('https://storage.test/document.pdf');

        // Another referral's document is refused on the direct download route.
        $this->actingAs($agencyUser)
            ->get(route('cases.documents.download', [$this->case->id, $otherReferralDoc->id]))
            ->assertForbidden();
    }

    public function test_agency_case_show_payload_scopes_documents_and_attachments(): void
    {
        $ownAgency = Agency::create(['id' => fake()->uuid(), 'name' => 'Own', 'short' => 'OW', 'slug' => 'own-payload']);
        $otherAgency = Agency::create(['id' => fake()->uuid(), 'name' => 'Other', 'short' => 'OT', 'slug' => 'other-payload']);
        $ownReferral = Referral::create([
            'id' => fake()->uuid(), 'required_services' => 'Service', 'status' => 'PENDING',
            'case_id' => $this->case->id, 'agcy_id' => $ownAgency->id,
        ]);
        $otherReferral = Referral::create([
            'id' => fake()->uuid(), 'required_services' => 'Other service', 'status' => 'PENDING',
            'case_id' => $this->case->id, 'agcy_id' => $otherAgency->id,
        ]);

        $generalDoc = $this->createDocument();
        $ownReferralDoc = $this->createDocument();
        $ownReferralDoc->update(['referral_id' => $ownReferral->id]);
        $otherReferralDoc = $this->createDocument();
        $otherReferralDoc->update(['referral_id' => $otherReferral->id]);

        $ownReferral->attachments()->create([
            'id' => fake()->uuid(), 'file_name' => 'own.txt', 'file_path' => 'referral/own.txt',
            'file_type' => 'text/plain', 'user_id' => $this->caseManager->id,
        ]);
        $otherReferral->attachments()->create([
            'id' => fake()->uuid(), 'file_name' => 'secret.txt', 'file_path' => 'referral/secret.txt',
            'file_type' => 'text/plain', 'user_id' => $this->caseManager->id,
        ]);

        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $ownAgency->id]);

        $this->actingAs($agencyUser)
            ->get(route('cases.show', $this->case->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Case/Show')
                ->has('case.documents', 2)
                ->where('case.documents', fn ($docs) => collect($docs)->pluck('id')->contains($otherReferralDoc->id) === false)
                ->where('case.referrals', function ($referrals) use ($ownReferral, $otherReferral) {
                    $own = collect($referrals)->firstWhere('id', $ownReferral->id);
                    $other = collect($referrals)->firstWhere('id', $otherReferral->id);

                    return count($own['attachments'] ?? []) === 1
                        && ! array_key_exists('attachments', $other);
                })
            );
    }

    #[Test]
    public function test_document_category_is_stored_and_filterable(): void
    {
        Storage::fake('object-storage');

        $storage = $this->createMock(StorageService::class);
        $storage->method('validate')->willReturn([]);
        $storage->method('temporaryUrl')->willReturn('https://example.com/file.pdf');
        $storage->method('store')->willReturn(new FileStoreResult(
            path: 'case-documents/test/document.pdf',
            originalName: 'document.pdf',
            storedName: 'uuid-document.pdf',
            type: 'application/pdf',
            size: 2048,
            success: true,
        ));
        $this->app->instance(StorageService::class, $storage);

        $file = UploadedFile::fake()->create('document.pdf', 2048);

        $response = $this->actingAs($this->caseManager)
            ->postJson(route('cases.documents.store', $this->case->id), [
                'file' => $file,
                'category' => 'Medical',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('case_documents', [
            'case_id' => $this->case->id,
            'category' => 'Medical',
        ]);

        // Filter by category
        $response = $this->actingAs($this->caseManager)
            ->getJson(route('cases.documents.index', $this->case->id).'?category=Medical');

        $response->assertOk();
        $this->assertCount(1, $response->json());

        // Filter by non-matching category returns empty
        $response = $this->actingAs($this->caseManager)
            ->getJson(route('cases.documents.index', $this->case->id).'?category=Financial');

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    public function test_document_creation_stores_size()
    {
        Storage::fake('object-storage');

        // Mock StorageService to bypass deep MIME inspection (empty-content
        // fake uploads are detected as application/x-empty)
        $storage = $this->createMock(StorageService::class);
        $storage->method('validate')->willReturn([]);
        $storage->method('temporaryUrl')->willReturn('https://example.com/file.pdf');
        $storage->method('store')->willReturn(new FileStoreResult(
            path: 'case-documents/test/document.pdf',
            originalName: 'document.pdf',
            storedName: 'uuid-document.pdf',
            type: 'application/pdf',
            size: 2048,
            success: true,
        ));
        $this->app->instance(StorageService::class, $storage);

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
            'role' => UserRole::AGENCY->value,
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
