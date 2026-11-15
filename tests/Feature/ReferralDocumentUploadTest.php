<?php

namespace Tests\Feature;

use App\DTOs\FileStoreResult;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReferralDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private CaseFile $case;

    private Referral $referral;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::factory()->create();

        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->case = CaseFile::factory()->create([
            'user_id' => $caseManager->id,
            'status' => 'OPEN',
        ]);

        $this->case->categories()->attach(CaseCategory::factory()->create());
        $this->referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test Service',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);
    }

    #[Test]
    public function add_attachment_rejects_exe_file(): void
    {
        Storage::fake('object-storage');

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $file = UploadedFile::fake()->createWithContent('malware.exe', 'malicious content', 'application/x-msdownload');

        $response = $this->from('/some-page')->actingAsWithMfa($admin)->post(
            route('referrals.attachments.store', $this->referral),
            ['file' => $file],
        );

        $response->assertSessionHasErrors('file');
    }

    #[Test]
    public function add_attachment_accepts_valid_pdf(): void
    {
        Storage::fake('object-storage');

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $file = UploadedFile::fake()->createWithContent('document.pdf', '%PDF-1.4 test document', 'application/pdf');

        $response = $this->actingAsWithMfa($admin)->post(
            route('referrals.attachments.store', $this->referral),
            ['file' => $file],
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('referral_attachments', [
            'referral_id' => $this->referral->id,
        ]);
    }

    #[Test]
    public function attachment_endpoints_reject_missing_files_and_invalid_labels(): void
    {
        Storage::fake('object-storage');
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $attachment = $this->referral->attachments()->create([
            'file_name' => 'original.pdf', 'file_path' => 'referrals/original.pdf',
            'file_type' => 'application/pdf', 'size' => 10, 'user_id' => $admin->id,
        ]);
        $this->actingAsWithMfa($admin);

        $urls = [
            route('referrals.attachments.store', $this->referral),
            route('referrals.attachments.replace', [$this->referral, $attachment]),
        ];
        foreach ($urls as $url) {
            $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('file');
            $this->postJson($url, ['file' => 'not an upload'])->assertUnprocessable()->assertJsonValidationErrors('file');
            $this->postJson($url, [
                'file' => UploadedFile::fake()->createWithContent('document.pdf', '%PDF-1.4 test document'),
                'document_label' => ['invalid'],
            ])->assertUnprocessable()->assertJsonValidationErrors('document_label');
        }
        $this->assertDatabaseCount('referral_attachments', 1);
        $this->assertFalse((bool) $attachment->fresh()->is_archived);
        $this->assertSame([], Storage::disk('object-storage')->allFiles());
    }

    #[Test]
    public function stale_replacement_keeps_current_version_and_removes_rejected_upload(): void
    {
        Storage::fake('object-storage');
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $original = $this->referral->attachments()->create([
            'file_name' => 'original.pdf', 'file_path' => 'referrals/original.pdf',
            'file_type' => 'application/pdf', 'size' => 10, 'user_id' => $admin->id,
        ]);
        $this->actingAsWithMfa($admin);
        $url = route('referrals.attachments.replace', [$this->referral, $original]);
        $file = fn () => UploadedFile::fake()->createWithContent('replacement.pdf', '%PDF-1.4 replacement');

        $this->post($url, ['file' => $file()])->assertRedirect()->assertSessionHasNoErrors();
        $storedPaths = Storage::disk('object-storage')->allFiles();
        $this->postJson($url, ['file' => $file()])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('referral_attachments', 2);
        $this->assertSame(1, $this->referral->attachments()->where('is_archived', false)->count());
        $this->assertSame($storedPaths, Storage::disk('object-storage')->allFiles());
    }

    #[Test]
    public function store_rejects_php_file(): void
    {
        Storage::fake('object-storage');

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $file = UploadedFile::fake()->createWithContent('shell.php', '<?php echo "hello"; ?>', 'application/x-php');

        $response = $this->from('/some-page')->actingAsWithMfa($admin)->post(
            route('referrals.store'),
            [
                'concern' => 'Assistance requested',
                'override_reason' => 'Agency selected for this assistance',
                'case_id' => $this->case->id,
                'agcy_ids' => [$this->agency->id],
                'documents' => [$file],
            ],
        );

        $response->assertSessionHasErrors('documents.0');
    }

    #[Test]
    public function store_accepts_valid_pdf(): void
    {
        Storage::fake('object-storage');

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $file = UploadedFile::fake()->createWithContent('document.pdf', '%PDF-1.4 test document', 'application/pdf');

        $response = $this->actingAsWithMfa($admin)->post(
            route('referrals.store'),
            [
                'concern' => 'Assistance requested',
                'override_reason' => 'Agency selected for this assistance',
                'case_id' => $this->case->id,
                'agcy_ids' => [$this->agency->id],
                'documents' => [$file],
            ],
        );

        $response->assertRedirect();
    }

    #[Test]
    public function store_rolls_back_referral_when_document_upload_fails(): void
    {
        Storage::fake('object-storage');

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        // Simulate an object-storage failure for a file that passes validation.
        $storage = $this->createMock(StorageService::class);
        $storage->method('store')->willReturn(new FileStoreResult(
            path: '',
            originalName: 'document.pdf',
            storedName: '',
            type: 'application/pdf',
            size: 2048,
            success: false,
            error: 'Storage error: simulated outage.',
        ));
        $this->app->instance(StorageService::class, $storage);

        // A fresh case with no existing referral to the target agency.
        $case = CaseFile::factory()->create([
            'user_id' => $admin->id,
            'status' => 'OPEN',
        ]);

        $case->categories()->attach(CaseCategory::factory()->create());
        $file = UploadedFile::fake()->createWithContent('document.pdf', '%PDF-1.4 test document', 'application/pdf');

        $response = $this->from('/referrals/create')->actingAsWithMfa($admin)->post(
            route('referrals.store'),
            [
                'concern' => 'Assistance requested',
                'override_reason' => 'Agency selected for this assistance',
                'case_id' => $case->id,
                'agcy_ids' => [$this->agency->id],
                'documents' => [$file],
            ],
        );

        $response->assertSessionHasErrors('documents');

        // A failed upload must not leave a committed referral (or its documents) behind.
        $this->assertDatabaseMissing('referrals', [
            'case_id' => $case->id,
            'agcy_id' => $this->agency->id,
        ]);
        $this->assertDatabaseCount('case_documents', 0);
    }
}
