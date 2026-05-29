<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralAttachment;
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
    public function agency_user_can_view_referral_show_page(): void
    {
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->agency->id,
        ]);

        $response = $this->actingAs($agencyUser)->get(route('referrals.show', $this->referral));

        $response->assertOk();
    }

    #[Test]
    public function case_manager_can_view_referral_show_page(): void
    {
        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($caseManager)->get(route('referrals.show', $this->referral));

        $response->assertOk();
    }

    #[Test]
    public function agency_user_cannot_upload_attachment(): void
    {
        Storage::fake('public');

        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->agency->id,
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($agencyUser)->post(
            route('referrals.attachments.store', $this->referral),
            ['file' => $file],
        );

        // AGENCY users should be forbidden from uploading attachments
        // Implementation to be added in Task 4
        $response->assertForbidden();
    }

    #[Test]
    public function agency_user_cannot_replace_attachment(): void
    {
        Storage::fake('public');

        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $attachment = ReferralAttachment::create([
            'id' => fake()->uuid(),
            'referral_id' => $this->referral->id,
            'file_name' => 'original.pdf',
            'file_path' => 'referrals/original.pdf',
            'file_type' => 'application/pdf',
            'size' => 100,
            'user_id' => $caseManager->id,
        ]);

        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->agency->id,
        ]);

        $file = UploadedFile::fake()->create('replacement.pdf', 100);

        $response = $this->actingAs($agencyUser)->post(
            route('referrals.attachments.replace', [$this->referral, $attachment->id]),
            ['file' => $file],
        );

        // AGENCY users should be forbidden from replacing attachments
        // Implementation to be added in Task 4
        $response->assertForbidden();
    }

    #[Test]
    public function case_manager_can_upload_attachment(): void
    {
        Storage::fake('public');

        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($caseManager)->post(
            route('referrals.attachments.store', $this->referral),
            ['file' => $file],
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('referral_attachments', [
            'referral_id' => $this->referral->id,
        ]);
    }
}
