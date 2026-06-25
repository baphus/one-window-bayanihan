<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
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

        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $this->case = CaseFile::factory()->create([
            'user_id' => $caseManager->id,
            'status' => 'OPEN',
        ]);

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
        Storage::fake('supabase');

        $admin = User::factory()->create(['role' => 'ADMIN']);

        $file = UploadedFile::fake()->create('malware.exe', 100);

        $response = $this->from('/some-page')->actingAs($admin)->post(
            route('referrals.attachments.store', $this->referral),
            ['file' => $file],
        );

        $response->assertSessionHasErrors('file');
    }

    #[Test]
    public function add_attachment_accepts_valid_pdf(): void
    {
        Storage::fake('supabase');

        $admin = User::factory()->create(['role' => 'ADMIN']);

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)->post(
            route('referrals.attachments.store', $this->referral),
            ['file' => $file],
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('referral_attachments', [
            'referral_id' => $this->referral->id,
        ]);
    }

    #[Test]
    public function store_rejects_php_file(): void
    {
        Storage::fake('supabase');

        $admin = User::factory()->create(['role' => 'ADMIN']);

        $file = UploadedFile::fake()->create('shell.php', 100);

        $response = $this->from('/some-page')->actingAs($admin)->post(
            route('referrals.store'),
            [
                'case_id' => $this->case->id,
                'agcy_id' => $this->agency->id,
                'required_services' => 'Test',
                'documents' => [$file],
            ],
        );

        $response->assertSessionHasErrors('documents.0');
    }

    #[Test]
    public function store_accepts_valid_pdf(): void
    {
        Storage::fake('supabase');

        $admin = User::factory()->create(['role' => 'ADMIN']);

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)->post(
            route('referrals.store'),
            [
                'case_id' => $this->case->id,
                'agcy_id' => $this->agency->id,
                'required_services' => 'Test',
                'documents' => [$file],
            ],
        );

        $response->assertRedirect();
    }
}
