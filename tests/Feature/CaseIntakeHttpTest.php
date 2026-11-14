<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\IntakeDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CaseIntakeHttpTest extends TestCase
{
    use RefreshDatabase;

    private function ofw(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::OFW->value, 'first_name' => 'Account', 'last_name' => 'Owner',
            'date_of_birth' => '1990-06-15', 'sex' => 'MALE',
        ], $attributes));
    }

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

    public function test_ofw_case_detail_includes_current_intake_answers_and_only_live_case_documents(): void
    {
        Storage::fake('object-storage');
        $owner = $this->ofw([]);
        $otherOwner = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->withIntake()->create([
            'ofw_user_id' => $owner->id,
            'staff_summary' => 'Staff-only summary',
            'consent_evidence' => 'Private evidence',
            'nok_name' => 'Relative name',
            'employment_employer_name' => 'Current employer',
        ]);
        $ownDocument = $case->documents()->create([
            'file_name' => 'passport.pdf', 'file_path' => 'case/passport.pdf',
            'file_type' => 'application/pdf', 'category' => 'passport_front_page',
            'size' => 10, 'user_id' => $owner->id,
        ]);
        $case->documents()->create([
            'file_name' => 'deleted.pdf', 'file_path' => 'case/deleted.pdf',
            'file_type' => 'application/pdf', 'category' => 'oec', 'size' => 10,
            'user_id' => $owner->id, 'is_deleted' => true,
        ]);
        $otherCase = CaseFile::factory()->withIntake()->create(['ofw_user_id' => $otherOwner->id]);
        $otherCase->documents()->create([
            'file_name' => 'other.pdf', 'file_path' => 'case/other.pdf',
            'file_type' => 'application/pdf', 'category' => 'oec', 'size' => 10,
            'user_id' => $otherOwner->id,
        ]);

        $this->actingAsWithMfa($otherOwner)->get(route('ofw.case.show', $case))->assertForbidden();

        $this->actingAsWithMfa($owner)->get(route('ofw.case.show', $case))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('case.nok_name', 'Relative name')
                ->where('case.employment_employer_name', 'Current employer')
                ->missing('case.staff_summary')
                ->missing('case.consent_evidence')
                ->has('case.documents', 1)
                ->where('case.documents.0.id', $ownDocument->id)
                ->where('case.documents.0.download_url', route('cases.documents.download', [$case->id, $ownDocument->id]))
            );
    }

    public function test_case_manager_review_has_intake_answers_and_only_case_level_live_documents(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->create([
            'status' => CaseFile::STATUS_PENDING_REVIEW,
            'assigned_case_manager_id' => $manager->id,
            'staff_summary' => 'Private staff note',
            'nok_name' => 'Relative name',
        ]);
        $document = $case->documents()->create([
            'file_name' => 'passport.pdf', 'file_path' => 'case/passport.pdf',
            'file_type' => 'application/pdf', 'category' => 'passport_front_page',
            'size' => 10, 'user_id' => $manager->id,
        ]);

        $this->actingAsWithMfa($manager)->get(route('cases.review-pending'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cases.data.0.nok_name', 'Relative name')
                ->where('cases.data.0.staff_summary', 'Private staff note')
                ->has('cases.data.0.documents', 1)
                ->where('cases.data.0.documents.0.id', $document->id)
                ->where('cases.data.0.documents.0.download_url', route('cases.documents.download', [$case->id, $document->id]))
            );
    }

    public function test_public_submission_succeeds_with_server_date_and_no_client_profile(): void
    {
        Storage::fake('object-storage');
        $owner = $this->ofw(['profile_completed_at' => null]);
        $data = $this->payload();
        $response = $this->actingAsWithMfa($owner)->post(route('ofw.intake.store'), $data);
        $this->assertNotSame(url('/'), $response->headers->get('Location'), $response->getContent());
        $this->assertSame(route('ofw.dashboard'), $response->headers->get('Location'));
        $case = CaseFile::where('ofw_user_id', $owner->id)->sole();
        $this->assertSame(now(config('app.operating_timezone', 'Asia/Manila'))->toDateString(), $case->intake_date->toDateString());
        $this->assertSame($owner->email, $case->email);
        $this->assertSame('ONLINE', $case->consent_method);
        $this->assertSame($owner->id, $case->consent_accepted_by);
    }

    public function test_ofw_can_submit_through_the_staff_store_route(): void
    {
        Storage::fake('object-storage');
        $owner = $this->ofw([]);

        $this->actingAsWithMfa($owner)->post(route('cases.store'), $this->payload())->assertRedirect();
        $this->assertTrue(CaseFile::where('ofw_user_id', $owner->id)->exists());
    }

    public function test_public_email_is_stamped_from_verified_account_and_keeps_historical_snapshot(): void
    {
        Storage::fake('object-storage');
        $owner = $this->ofw(['email' => 'account@example.test']);
        $data = $this->payload();
        $data['email'] = 'forged@example.test';

        $this->actingAsWithMfa($owner)->post(route('ofw.intake.store'), $data)
            ->assertRedirect(route('ofw.dashboard'));

        $case = CaseFile::where('ofw_user_id', $owner->id)->sole();
        $this->assertSame('account@example.test', $case->email);
        $owner->update(['email' => 'new-verified@example.test']);
        $this->assertSame('account@example.test', $case->fresh()->email);
    }

    public function test_unverified_account_cannot_submit_public_intake(): void
    {
        $owner = $this->ofw(['email_verified_at' => null]);
        $this->actingAsWithMfa($owner)->post(route('ofw.intake.store'), $this->payload())->assertRedirect(route('verification.notice'));
        $this->assertSame(0, CaseFile::count());
    }

    public function test_public_submission_rejects_nok_and_provenance_fields(): void
    {
        $owner = $this->ofw([]);
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
        $owner = $this->ofw([]);
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

    public function test_staff_filing_opens_case_and_records_requirement_map(): void
    {
        Storage::fake('object-storage');
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $data = $this->payload();
        $data['documents'] = ['passport_front_page' => UploadedFile::fake()->image('passport.jpg')];
        $data['complied_onsite_slots'] = ['arrival_stamp'];
        $data['missing_document_reasons'] = ['sworn_affidavit' => 'To follow'];

        $this->actingAsWithMfa($manager)->post(route('cases.store'), $data)->assertRedirect();

        $case = CaseFile::sole();
        $this->assertSame('OPEN', $case->status);
        $this->assertSame('STAFF_ATTESTED', $case->consent_method);
        $this->assertSame($manager->id, $case->assigned_case_manager_id);
        $this->assertEquals([
            'passport_front_page' => IntakeDocumentService::REQUIREMENT_RECEIVED,
            'arrival_stamp' => IntakeDocumentService::REQUIREMENT_COMPLIED_ONSITE,
            'sworn_affidavit' => IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE,
            'employment_evidence' => IntakeDocumentService::REQUIREMENT_FOR_COMPLIANCE,
        ], $case->document_requirements);

        $this->actingAsWithMfa($manager)->get(route('cases.show', $case))
            ->assertInertia(fn (Assert $page) => $page
                ->has('documentRequirements', 4)
                ->where('documentRequirements', fn ($requirements) => collect($requirements)->contains(
                    fn ($requirement) => $requirement['slot'] === 'sworn_affidavit' && $requirement['note'] === 'To follow'
                )));
    }

    public function test_staff_filing_links_active_ofw_account_by_email(): void
    {
        Storage::fake('object-storage');
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $ofw = User::factory()->create(['role' => UserRole::OFW->value, 'email' => 'linked@example.test']);
        $data = $this->payload();
        $data['email'] = 'linked@example.test';

        $this->actingAsWithMfa($manager)->post(route('cases.store'), $data)->assertRedirect();

        $this->assertSame($ofw->id, CaseFile::sole()->ofw_user_id);
    }

    public function test_staff_filing_ignores_non_ofw_and_inactive_email_matches(): void
    {
        Storage::fake('object-storage');
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        User::factory()->create(['role' => UserRole::ADMIN->value, 'email' => 'admin@example.test']);
        User::factory()->create(['role' => UserRole::OFW->value, 'email' => 'inactive@example.test', 'is_active' => false]);

        foreach (['admin@example.test', 'inactive@example.test'] as $email) {
            $data = $this->payload();
            $data['email'] = $email;
            $this->actingAsWithMfa($manager)->post(route('cases.store'), $data)->assertRedirect();
        }

        $this->assertSame([null, null], CaseFile::orderBy('case_number')->pluck('ofw_user_id')->all());
    }

    public function test_ofw_lookup_returns_only_the_matched_active_ofw_name(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $ofw = User::factory()->create([
            'role' => UserRole::OFW->value, 'email' => 'lookup@example.test',
            'first_name' => 'Maria', 'last_name' => 'Cruz',
        ]);

        $this->actingAsWithMfa($manager)
            ->getJson(route('cases.ofw-lookup', ['email' => 'lookup@example.test']))
            ->assertOk()->assertExactJson(['name' => $ofw->name]);

        $this->actingAsWithMfa($manager)
            ->getJson(route('cases.ofw-lookup', ['email' => 'nobody@example.test']))
            ->assertOk()->assertExactJson(['name' => null]);

        $this->actingAsWithMfa($ofw)
            ->getJson(route('cases.ofw-lookup', ['email' => 'lookup@example.test']))
            ->assertForbidden();
    }
}
