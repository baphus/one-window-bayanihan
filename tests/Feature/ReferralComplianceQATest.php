<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralComplianceRequirement;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * F3 QA — For Compliance Feature
 *
 * End-to-end scenarios verifying the For Compliance feature works correctly.
 */
class ReferralComplianceQATest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private CaseFile $case;

    private User $admin;

    private User $caseManager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->agency = Agency::factory()->create();
        $this->caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->admin = User::factory()->create(['role' => 'ADMIN']);

        $this->case = CaseFile::factory()->create([
            'user_id' => $this->caseManager->id,
            'status' => 'OPEN',
        ]);
    }

    /* =====================================================================
     * Scenario A: Backward compatibility — all documents uploaded
     *
     * Create a referral with all required document uploads (no compliance).
     * Verify:
     *   1. Status is PENDING
     *   2. No compliance requirement records exist
     * ===================================================================== */

    #[Test]
    public function scenario_a_all_documents_uploaded_no_compliance(): void
    {
        $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('referrals.store'), [
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
            'required_services' => 'Test Service A',
            'notes' => 'Scenario A: all docs, no compliance',
            'documents' => [$pdf],
        ]);

        $response->assertRedirect();

        // 1. Verify status is PENDING
        $referral = Referral::where('required_services', 'Test Service A')->first();
        $this->assertNotNull($referral, 'Referral should be created');
        $this->assertEquals('PENDING', $referral->status, 'Status should be PENDING when no compliance requirements');

        // 2. Verify no compliance requirement records exist
        $this->assertEquals(
            0,
            $referral->complianceRequirements()->count(),
            'No compliance requirements should exist when all documents are uploaded'
        );

        // 3. Verify attachment was created
        $this->assertEquals(
            1,
            $referral->attachments()->count(),
            'One attachment should exist for the uploaded document'
        );
    }

    /* =====================================================================
     * Scenario B: Mixed upload/compliance creation
     *
     * Create a referral with some docs uploaded and some marked For Compliance.
     * Verify:
     *   1. Status is FOR_COMPLIANCE
     *   2. Compliance requirements were created for the For Compliance items
     *   3. Attachments were created for the uploaded items only
     * ===================================================================== */

    #[Test]
    public function scenario_b_mixed_upload_and_compliance(): void
    {
        $pdf = UploadedFile::fake()->create('uploaded-doc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('referrals.store'), [
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
            'required_services' => 'Test Service B',
            'notes' => 'Scenario B: mixed upload and compliance',
            'documents' => ['uploaded_requirement' => $pdf],
            'compliance_requirements' => [
                [
                    'service_name' => 'Service B',
                    'requirement_name' => 'NBI Clearance',
                ],
                [
                    'service_name' => 'Service B',
                    'requirement_name' => 'Police Clearance',
                ],
            ],
        ]);

        $response->assertRedirect();

        $referral = Referral::where('required_services', 'Test Service B')->first();
        $this->assertNotNull($referral);

        // 1. Verify status is FOR_COMPLIANCE
        $this->assertEquals(
            'FOR_COMPLIANCE',
            $referral->status,
            'Status should be FOR_COMPLIANCE when compliance requirements are specified'
        );

        // 2. Verify compliance requirements were created
        $requirements = $referral->complianceRequirements;
        $this->assertCount(2, $requirements, 'Two compliance requirements should exist');

        $reqNames = $requirements->pluck('requirement_name')->toArray();
        $this->assertContains('NBI Clearance', $reqNames);
        $this->assertContains('Police Clearance', $reqNames);

        // Verify each requirement starts as PENDING
        foreach ($requirements as $req) {
            $this->assertEquals('PENDING', $req->status, 'Each requirement should start as PENDING');
            $this->assertNull($req->completed_at, 'completed_at should be null initially');
            $this->assertNull($req->fulfilled_by, 'fulfilled_by should be null initially');
        }

        // 3. Verify attachment was created for the uploaded item only
        $this->assertEquals(
            1,
            $referral->attachments()->count(),
            'Only one attachment should exist (the uploaded document, not the compliance items)'
        );
    }

    /* =====================================================================
     * Scenario C: Fulfill a compliance requirement
     *
     * Take a referral with a PENDING compliance requirement, POST to the
     * fulfill endpoint with a valid file. Verify:
     *   1. Requirement is now COMPLIED
     *   2. An attachment was created
     *   3. completed_at and fulfilled_by are set
     * ===================================================================== */

    #[Test]
    public function scenario_c_fulfill_compliance_requirement(): void
    {
        // Create a referral with one compliance requirement
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test Service C',
            'status' => 'FOR_COMPLIANCE',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $requirement = ReferralComplianceRequirement::create([
            'referral_id' => $referral->id,
            'service_name' => 'Service C',
            'requirement_name' => 'NBI Clearance',
            'status' => 'PENDING',
        ]);

        // Fulfill with a valid PDF
        $pdf = UploadedFile::fake()->create('nbi-clearance.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(
            route('referrals.compliance.fulfill', [$referral, $requirement]),
            ['file' => $pdf],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 1. Verify requirement is now COMPLIED
        $requirement->refresh();
        $this->assertEquals('COMPLIED', $requirement->status, 'Requirement should be COMPLIED after fulfillment');

        // 2. Verify an attachment was created
        $this->assertEquals(
            1,
            $referral->attachments()->count(),
            'An attachment should be created for the fulfilled requirement'
        );

        // 3. Verify completed_at and fulfilled_by are set
        $this->assertNotNull($requirement->completed_at, 'completed_at should be set on fulfillment');
        $this->assertEquals($this->admin->id, $requirement->fulfilled_by, 'fulfilled_by should be the fulfilling user');
    }

    /* =====================================================================
     * Scenario D: Cannot re-fulfill already COMPLIED requirement
     *
     * Take a COMPLIED requirement, attempt to fulfill it again.
     * Verify:
     *   1. Error/validation prevents it
     * ===================================================================== */

    #[Test]
    public function scenario_d_cannot_re_fulfill_complied_requirement(): void
    {
        // Create a referral with a requirement already in COMPLIED status
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test Service D',
            'status' => 'FOR_COMPLIANCE',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $requirement = ReferralComplianceRequirement::create([
            'referral_id' => $referral->id,
            'service_name' => 'Service D',
            'requirement_name' => 'Police Clearance',
            'status' => 'COMPLIED',
            'fulfilled_by' => $this->admin->id,
            'completed_at' => now(),
        ]);

        // Attempt to fulfill again via HTTP
        $pdf = UploadedFile::fake()->create('police-clearance.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(
            route('referrals.compliance.fulfill', [$referral, $requirement]),
            ['file' => $pdf],
        );

        // The controller does NOT catch InvalidArgumentException from the service,
        // so it results in a 500 response in debug mode, or a redirect in production.
        // Either way, verify requirement is still COMPLIED (not overwritten)
        $requirement->refresh();
        $this->assertEquals('COMPLIED', $requirement->status, 'Requirement should remain COMPLIED after failed re-fulfill attempt');
        $this->assertNotNull($requirement->completed_at, 'completed_at should still be set');
        $this->assertEquals($this->admin->id, $requirement->fulfilled_by, 'fulfilled_by should still be set');
    }

    #[Test]
    public function scenario_d_service_layer_guard(): void
    {
        // Test the service layer guard directly (bypass HTTP for deterministic assertion)
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test Service D2',
            'status' => 'FOR_COMPLIANCE',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $requirement = ReferralComplianceRequirement::create([
            'referral_id' => $referral->id,
            'service_name' => 'Service D',
            'requirement_name' => 'Police Clearance',
            'status' => 'COMPLIED',
            'fulfilled_by' => $this->admin->id,
            'completed_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Compliance requirement is not pending.');

        $service = $this->app->make(ReferralService::class);
        $service->fulfillCompliance(
            $requirement->id,
            ['name' => 'test.pdf', 'path' => 'test.pdf', 'type' => 'application/pdf', 'size' => 100],
            $this->admin->id,
        );
    }

    /* =====================================================================
     * Scenario E: Invalid file type rejected
     *
     * POST to fulfill with .exe file.
     * Verify:
     *   1. 422 validation error
     * ===================================================================== */

    #[Test]
    public function scenario_e_invalid_file_type_rejected(): void
    {
        // Create a referral with a PENDING requirement
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test Service E',
            'status' => 'FOR_COMPLIANCE',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $requirement = ReferralComplianceRequirement::create([
            'referral_id' => $referral->id,
            'service_name' => 'Service E',
            'requirement_name' => 'NBI Clearance',
            'status' => 'PENDING',
        ]);

        // Attempt to fulfill with .exe file
        $exe = UploadedFile::fake()->create('malware.exe', 100);

        $response = $this->from(route('referrals.show', $referral))->actingAs($this->admin)->post(
            route('referrals.compliance.fulfill', [$referral, $requirement]),
            ['file' => $exe],
        );

        // Verify validation error (web context returns redirect with session errors)
        $response->assertSessionHasErrors('file');
        $response->assertRedirect(route('referrals.show', $referral));

        // Verify requirement is still PENDING
        $requirement->refresh();
        $this->assertEquals('PENDING', $requirement->status, 'Requirement should remain PENDING after failed upload');
    }
}
