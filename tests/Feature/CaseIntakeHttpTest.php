<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\IntakeDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaseIntakeHttpTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'client_type' => 'OFW', 'intake_date' => '2026-11-10',
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'date_of_birth' => '1990-06-15',
            'sex' => 'MALE', 'email' => 'juan@example.test', 'contact_number' => '09171234567',
            'civil_status' => 'SINGLE', 'deployment_mode' => 'AGENCY_HIRED', 'employment_status' => 'RETURNED',
            'address_region' => 'Central Visayas', 'address_city_municipality' => 'Cebu City', 'address_barangay' => 'Lahug',
            'employment_employer_name' => 'Example Employer', 'employment_country' => 'Japan',
            'summary' => 'Assistance', 'vulnerability_indicator' => 'None',
            'return_reason' => 'OTHERS', 'consent' => true, 'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
            'documents' => collect(['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'])
                ->mapWithKeys(fn ($slot) => [$slot => UploadedFile::fake()->image($slot.'.jpg')])->all(),
        ];
    }

    public function test_public_submission_succeeds_with_server_date_and_no_client_profile(): void
    {
        Storage::fake('object-storage');
        $owner = User::factory()->create(['role' => UserRole::OFW->value, 'profile_completed_at' => null]);
        $data = $this->payload();
        $response = $this->actingAsWithMfa($owner)->post(route('ofw.intake.store'), $data);
        $this->assertNotSame(url('/'), $response->headers->get('Location'), $response->getContent());
        $this->assertSame(route('ofw.dashboard'), $response->headers->get('Location'));
        $case = CaseFile::where('ofw_user_id', $owner->id)->sole();
        $this->assertSame(now(config('app.operating_timezone', 'Asia/Manila'))->toDateString(), $case->intake_date->toDateString());
        $this->assertSame('ONLINE', $case->consent_method);
        $this->assertSame($owner->id, $case->consent_accepted_by);
    }

    public function test_unverified_account_cannot_submit_public_intake(): void
    {
        $owner = User::factory()->create(['role' => UserRole::OFW->value, 'email_verified_at' => null]);
        $this->actingAsWithMfa($owner)->post(route('ofw.intake.store'), $this->payload())->assertRedirect(route('verification.notice'));
        $this->assertSame(0, CaseFile::count());
    }

    public function test_public_submission_rejects_nok_and_provenance_fields(): void
    {
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        foreach ([
            ['client_type' => 'NEXT_OF_KIN', 'nok_name' => 'Relative'],
            ['ofw_user_id' => User::factory()->create()->id],
            ['assigned_case_manager_id' => User::factory()->create(['role' => UserRole::CASE_MANAGER->value])->id],
            ['consent_method' => 'WRITTEN', 'consent_evidence' => 'Forged'],
            ['missing_document_reasons' => ['arrival_stamp' => 'Skip']],
        ] as $override) {
            $this->actingAsWithMfa($owner)->post(route('ofw.intake.store'), array_merge($this->payload(), $override))
                ->assertSessionHasErrors(array_key_first($override));
        }
        $this->assertSame(0, CaseFile::count());
    }

    public function test_public_submission_without_consent_or_required_documents_fails(): void
    {
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $data = $this->payload();
        unset($data['consent']);
        $this->actingAsWithMfa($owner)->post(route('ofw.intake.store'), $data)->assertSessionHasErrors('consent');
        $data = $this->payload();
        unset($data['documents']['passport_front_page']);
        $this->actingAsWithMfa($owner)->post(route('ofw.intake.store'), $data)->assertSessionHasErrors('documents.passport_front_page');
        $this->assertSame(0, CaseFile::count());
    }

    public function test_correction_route_denies_unassigned_manager_and_allows_assigned(): void
    {
        $assigned = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $unassigned = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->create(['assigned_case_manager_id' => $assigned->id]);
        $this->actingAsWithMfa($unassigned)->patch(route('cases.update', $case), ['first_name' => 'Nope', 'correction_reason' => 'Attempt'])->assertForbidden();
        $this->actingAsWithMfa($assigned)->patch(route('cases.update', $case), ['first_name' => 'Corrected', 'correction_reason' => 'Verified record'])->assertRedirect();
        $this->assertSame('Corrected', $case->fresh()->first_name);
    }

    public function test_correction_requires_reason(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->create(['assigned_case_manager_id' => $manager->id]);
        $this->actingAsWithMfa($manager)->patch(route('cases.update', $case), ['first_name' => 'Nope'])->assertSessionHasErrors('correction_reason');
        $this->assertSame('Juan', $case->fresh()->first_name);
    }
}
