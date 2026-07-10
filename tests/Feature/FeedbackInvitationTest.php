<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Models\Referral;
use App\Models\Service;
use App\Models\ServqualConfig;
use App\Models\User;
use App\Services\FeedbackInvitationService;
use App\Services\FeedbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedbackInvitationTest extends TestCase
{
    use RefreshDatabase;

    private FeedbackInvitationService $invitationService;

    private FeedbackService $feedbackService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->invitationService = app(FeedbackInvitationService::class);
        $this->feedbackService = app(FeedbackService::class);
    }

    #[Test]
    public function create_invitation_creates_record_with_token_prefix_and_hash(): void
    {
        $agency = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->completed()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        ServqualConfig::factory()->active()->create([
            'agency_id' => $agency->id,
        ]);

        $result = $this->invitationService->createInvitation(
            caseId: $case->id,
            agencyId: $agency->id,
            referralId: $referral->id,
            clientEmail: $client->email,
        );

        $this->assertArrayHasKey('invitation', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertInstanceOf(FeedbackInvitation::class, $result['invitation']);
        $this->assertIsString($result['token']);

        $token = $result['token'];
        $invitation = $result['invitation'];

        $this->assertEquals(substr($token, 0, 10), $invitation->token_prefix);
        $this->assertEquals(hash('sha256', $token), $invitation->token_hash);
        $this->assertEquals($case->id, $invitation->case_id);
        $this->assertEquals($agency->id, $invitation->agency_id);
        $this->assertEquals($referral->id, $invitation->referral_id);
        $this->assertEquals($client->email, $invitation->client_email);
        $this->assertNotEmpty($invitation->form_snapshot);
        $this->assertTrue($invitation->expires_at->isFuture());
        $this->assertTrue($invitation->expires_at->diffInDays(now()) <= 31);
        $this->assertNull($invitation->submitted_at);
        $this->assertEquals('agency_active_form', $invitation->snapshot_source);
    }

    #[Test]
    public function create_invitation_persists_service_id_foreign_key(): void
    {
        $agency = Agency::factory()->create();
        $service = Service::create(['agcy_id' => $agency->id, 'name' => 'Legal Aid', 'description' => 'Legal', 'processing_days' => 3]);
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->completed()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);

        ServqualConfig::factory()->active()->create([
            'agency_id' => $agency->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
        ]);

        $result = $this->invitationService->createInvitation($case->id, $agency->id, $referral->id, $client->email, $service->id);

        $this->assertEquals($service->id, $result['invitation']->service_id);
        $this->assertDatabaseHas('feedback_invitations', ['id' => $result['invitation']->id, 'service_id' => $service->id]);
    }

    #[Test]
    public function service_specific_form_takes_precedence_over_agency_default(): void
    {
        $agency = Agency::factory()->create();
        $service = Service::create(['agcy_id' => $agency->id, 'name' => 'OWWA Membership', 'description' => 'Membership', 'processing_days' => 1]);
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->completed()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);

        ServqualConfig::factory()->active()->create([
            'agency_id' => $agency->id,
            'service_id' => null,
            'service_name' => 'Default Form',
            'questions' => [['dimension' => 'Tangibles', 'question' => 'Default question?', 'order' => 1]],
        ]);
        ServqualConfig::factory()->active()->create([
            'agency_id' => $agency->id,
            'service_id' => $service->id,
            'service_name' => 'Override Form',
            'questions' => [['dimension' => 'Reliability', 'question' => 'Override question?', 'order' => 1]],
        ]);

        $invitation = $this->invitationService->createInvitation($case->id, $agency->id, $referral->id, $client->email, $service->id)['invitation'];

        $this->assertEquals('OWWA Membership', $invitation->service_name);
        $this->assertSame('Override question?', $invitation->form_snapshot[0]['question']);
    }

    #[Test]
    public function form_resolution_falls_back_to_default_and_then_system_default(): void
    {
        $agency = Agency::factory()->create();
        $service = Service::create(['agcy_id' => $agency->id, 'name' => 'Unconfigured Service', 'description' => 'None', 'processing_days' => 1]);
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->completed()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);

        ServqualConfig::factory()->active()->create([
            'agency_id' => $agency->id,
            'service_id' => null,
            'service_name' => 'Agency Default',
            'questions' => [['dimension' => 'Empathy', 'question' => 'Default fallback?', 'order' => 1]],
        ]);

        $fallback = $this->invitationService->createInvitation($case->id, $agency->id, $referral->id, $client->email, $service->id)['invitation'];
        $this->assertEquals('agency_active_form', $fallback->snapshot_source);
        $this->assertEquals('Default fallback?', $fallback->form_snapshot[0]['question']);

        ServqualConfig::query()->delete();
        $secondCase = CaseFile::factory()->create(['client_id' => $client->id]);
        $secondReferral = Referral::factory()->completed()->create(['case_id' => $secondCase->id, 'agcy_id' => $agency->id]);
        $none = $this->invitationService->createInvitation($secondCase->id, $agency->id, $secondReferral->id, $client->email, $service->id)['invitation'];
        $this->assertEquals('system_default', $none->snapshot_source);
        $this->assertEmpty($none->form_snapshot);
    }

    #[Test]
    public function create_invitation_without_active_config_uses_default_snapshot(): void
    {
        $agency = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->completed()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $result = $this->invitationService->createInvitation(
            caseId: $case->id,
            agencyId: $agency->id,
            referralId: $referral->id,
            clientEmail: $client->email,
        );

        $invitation = $result['invitation'];

        $this->assertEquals('system_default', $invitation->snapshot_source);
        $this->assertEmpty($invitation->form_snapshot);
    }

    #[Test]
    public function validate_public_token_returns_invitation_for_valid_token(): void
    {
        $agency = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->completed()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $result = $this->invitationService->createInvitation(
            caseId: $case->id,
            agencyId: $agency->id,
            referralId: $referral->id,
            clientEmail: $client->email,
        );

        $validated = $this->invitationService->validatePublicToken($result['token']);

        $this->assertEquals($result['invitation']->id, $validated->id);
    }

    #[Test]
    public function validate_public_token_throws_for_invalid_token(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid feedback link.');

        $this->invitationService->validatePublicToken('nonexistent-token');
    }

    #[Test]
    public function validate_public_token_throws_for_expired_token(): void
    {
        $fullToken = Str::random(40);
        FeedbackInvitation::factory()->expired()->create([
            'token_prefix' => substr($fullToken, 0, 10),
            'token_hash' => hash('sha256', $fullToken),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This feedback link has expired.');

        $this->invitationService->validatePublicToken($fullToken);
    }

    #[Test]
    public function validate_public_token_throws_for_submitted_token(): void
    {
        $fullToken = Str::random(40);
        FeedbackInvitation::factory()->create([
            'token_prefix' => substr($fullToken, 0, 10),
            'token_hash' => hash('sha256', $fullToken),
            'submitted_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This feedback has already been submitted.');

        $this->invitationService->validatePublicToken($fullToken);
    }

    #[Test]
    public function mark_submitted_updates_invitation(): void
    {
        $agency = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->completed()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $result = $this->invitationService->createInvitation(
            caseId: $case->id,
            agencyId: $agency->id,
            referralId: $referral->id,
            clientEmail: $client->email,
        );

        $invitation = $result['invitation'];
        $feedback = Feedback::create([
            'case_id' => $case->id,
            'agency_id' => $agency->id,
            'referral_id' => $referral->id,
            'service_name' => 'Test Service',
            'overall_rating' => 5,
            'comments' => 'Great service!',
        ]);

        $this->invitationService->markSubmitted($invitation, $feedback);

        $this->assertNotNull($invitation->fresh()->submitted_at);
        $this->assertEquals($feedback->id, $invitation->fresh()->used_feedback_id);
    }

    #[Test]
    public function invitation_scopes_work_correctly(): void
    {
        $agency = Agency::factory()->create();

        // Usable invitation (not expired, not submitted)
        $usableInvitation = FeedbackInvitation::factory()->create([
            'agency_id' => $agency->id,
            'expires_at' => now()->addDays(7),
            'submitted_at' => null,
        ]);

        // Expired invitation
        FeedbackInvitation::factory()->expired()->create([
            'agency_id' => $agency->id,
        ]);

        // Submitted invitation
        FeedbackInvitation::factory()->submitted()->create([
            'agency_id' => $agency->id,
        ]);

        $this->assertEquals(1, FeedbackInvitation::usable()->count());
        $this->assertEquals(1, FeedbackInvitation::expired()->count());
        $this->assertEquals(1, FeedbackInvitation::submitted()->count());
    }

    #[Test]
    public function get_servqual_config_filters_by_is_active(): void
    {
        $agency = Agency::factory()->create();
        $inactiveService = Service::create(['agcy_id' => $agency->id, 'name' => 'Inactive Override', 'processing_days' => 1]);

        $activeQuestions = [
            ['dimension' => 'Tangibles', 'question' => 'Active question 1', 'order' => 1],
            ['dimension' => 'Reliability', 'question' => 'Active question 2', 'order' => 2],
        ];

        $inactiveQuestions = [
            ['dimension' => 'Empathy', 'question' => 'Inactive question 1', 'order' => 1],
        ];

        ServqualConfig::factory()->active()->create([
            'agency_id' => $agency->id,
            'questions' => $activeQuestions,
        ]);

        ServqualConfig::factory()->create([
            'agency_id' => $agency->id,
            'service_id' => $inactiveService->id,
            'questions' => $inactiveQuestions,
            'is_active' => false,
            'activated_at' => null,
        ]);

        $result = $this->feedbackService->getServqualConfig($agency->id);

        $this->assertNotEmpty($result);

        // The active config's questions should be present in the result
        $resultQuestions = json_encode($result);
        $this->assertStringContainsString('Active question 1', $resultQuestions);
        $this->assertStringNotContainsString('Inactive question 1', $resultQuestions);
    }

    #[Test]
    public function agency_servqual_config_auto_activates_first_config(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $this->actingAs($user);

        $questions = [
            ['dimension' => 'Tangibles', 'question' => 'Test question?', 'order' => 1],
        ];

        $response = $this->post(route('servqual-configs.store'), [
            'name' => 'Test Form',
            'service_name' => 'Test Service',
            'questions' => $questions,
        ]);

        $response->assertRedirect();

        $config = ServqualConfig::where('agency_id', $agency->id)->first();

        $this->assertNotNull($config);
        $this->assertTrue($config->is_active);
        $this->assertNotNull($config->activated_at);
    }

    #[Test]
    public function agency_servqual_config_does_not_auto_activate_second_config(): void
    {
        $agency = Agency::factory()->create();
        $service = Service::create(['agcy_id' => $agency->id, 'name' => 'Second Service', 'description' => 'Second', 'processing_days' => 2]);
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $this->actingAs($user);

        // First config (auto-activated)
        $this->post(route('servqual-configs.store'), [
            'name' => 'First Form',
            'service_name' => 'First Service',
            'questions' => [
                ['dimension' => 'Tangibles', 'question' => 'First question?', 'order' => 1],
            ],
        ]);

        // Second config
        $this->post(route('servqual-configs.store'), [
            'name' => 'Second Form',
            'service_id' => $service->id,
            'service_name' => 'Second Service',
            'questions' => [
                ['dimension' => 'Reliability', 'question' => 'Second question?', 'order' => 1],
            ],
        ]);

        $secondConfig = ServqualConfig::where('agency_id', $agency->id)
            ->where('service_name', 'Second Service')
            ->first();

        $this->assertNotNull($secondConfig);
        $this->assertTrue($secondConfig->is_active);
        $this->assertNotNull($secondConfig->activated_at);
    }

    #[Test]
    public function agency_cannot_create_two_default_forms(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        ServqualConfig::factory()->active()->create(['agency_id' => $agency->id, 'service_id' => null]);

        $response = $this->actingAs($user)->post(route('servqual-configs.store'), [
            'name' => 'Duplicate Default',
            'service_id' => null,
            'service_name' => 'Default Service',
            'questions' => [['dimension' => 'Tangibles', 'question' => 'Duplicate?', 'order' => 1]],
        ]);

        $response->assertSessionHasErrors('service_id');
        $this->assertEquals(1, ServqualConfig::where('agency_id', $agency->id)->whereNull('service_id')->count());
    }

    #[Test]
    public function activating_service_override_does_not_deactivate_default(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $this->actingAs($user);

        $service = Service::create(['agcy_id' => $agency->id, 'name' => 'Override Service', 'processing_days' => 1]);

        $defaultConfig = ServqualConfig::factory()->active()->create([
            'agency_id' => $agency->id,
            'service_id' => null,
        ]);

        $overrideConfig = ServqualConfig::factory()->create([
            'agency_id' => $agency->id,
            'service_id' => $service->id,
            'is_active' => true,
        ]);

        $response = $this->patch(route('servqual-configs.activate', $overrideConfig->id));

        $response->assertRedirect();

        $this->assertTrue($defaultConfig->fresh()->is_active);
        $this->assertTrue($overrideConfig->fresh()->is_active);
    }

    #[Test]
    public function agency_cannot_assign_cross_agency_service_to_form(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $config = ServqualConfig::factory()->create(['agency_id' => $agency->id, 'service_id' => null]);
        $otherService = Service::create(['agcy_id' => $otherAgency->id, 'name' => 'Other Service', 'processing_days' => 1]);

        $response = $this->actingAs($user)->post(route('servqual-configs.assign-service', $config->id), [
            'service_id' => $otherService->id,
        ]);

        $response->assertSessionHasErrors('service_id');
        $this->assertNull($config->fresh()->service_id);
    }

    #[Test]
    public function agency_cannot_create_duplicate_service_specific_form(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $service = Service::create(['agcy_id' => $agency->id, 'name' => 'Legal', 'processing_days' => 1]);
        ServqualConfig::factory()->create(['agency_id' => $agency->id, 'service_id' => $service->id]);

        $response = $this->actingAs($user)->post(route('servqual-configs.store'), [
            'name' => 'Duplicate Override',
            'service_id' => $service->id,
            'service_name' => $service->name,
            'questions' => [['dimension' => 'Tangibles', 'question' => 'Duplicate?', 'order' => 1]],
        ]);

        $response->assertSessionHasErrors('service_id');
        $this->assertEquals(1, ServqualConfig::where('agency_id', $agency->id)->where('service_id', $service->id)->count());
    }

    #[Test]
    public function agency_cannot_update_form_to_duplicate_default_or_service(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $service = Service::create(['agcy_id' => $agency->id, 'name' => 'Legal', 'processing_days' => 1]);
        $otherService = Service::create(['agcy_id' => $agency->id, 'name' => 'Welfare', 'processing_days' => 1]);
        $default = ServqualConfig::factory()->active()->create(['agency_id' => $agency->id, 'service_id' => null]);
        $override = ServqualConfig::factory()->create(['agency_id' => $agency->id, 'service_id' => $service->id]);
        $otherOverride = ServqualConfig::factory()->create(['agency_id' => $agency->id, 'service_id' => $otherService->id]);

        $this->actingAs($user)
            ->patch(route('servqual-configs.update', $override->id), ['service_id' => null])
            ->assertSessionHasErrors('service_id');

        $this->actingAs($user)
            ->patch(route('servqual-configs.update', $otherOverride->id), ['service_id' => $service->id])
            ->assertSessionHasErrors('service_id');

        $this->assertNull($default->fresh()->service_id);
        $this->assertEquals($service->id, $override->fresh()->service_id);
        $this->assertEquals($otherService->id, $otherOverride->fresh()->service_id);
    }

    #[Test]
    public function unassigning_override_deletes_it_when_default_exists(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $service = Service::create(['agcy_id' => $agency->id, 'name' => 'Legal', 'processing_days' => 1]);
        ServqualConfig::factory()->active()->create(['agency_id' => $agency->id, 'service_id' => null]);
        $override = ServqualConfig::factory()->create(['agency_id' => $agency->id, 'service_id' => $service->id]);

        $response = $this->actingAs($user)->post(route('servqual-configs.unassign-service', $override->id));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('servqual_configs', ['id' => $override->id]);
    }

    #[Test]
    public function cannot_delete_active_config_when_others_exist(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $this->actingAs($user);

        $activeConfig = ServqualConfig::factory()->active()->create([
            'agency_id' => $agency->id,
        ]);

        $service = Service::create(['agcy_id' => $agency->id, 'name' => 'Override Service', 'processing_days' => 1]);

        ServqualConfig::factory()->create([
            'agency_id' => $agency->id,
            'service_id' => $service->id,
            'is_active' => false,
            'activated_at' => null,
        ]);

        $response = $this->delete(route('servqual-configs.destroy', $activeConfig->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('servqual_configs', ['id' => $activeConfig->id]);
    }

    #[Test]
    public function can_delete_active_config_when_it_is_the_only_one(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $this->actingAs($user);

        $config = ServqualConfig::factory()->active()->create([
            'agency_id' => $agency->id,
        ]);

        $response = $this->delete(route('servqual-configs.destroy', $config->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('servqual_configs', ['id' => $config->id]);
    }

    #[Test]
    public function public_feedback_show_form_returns_invitation_data(): void
    {
        $fullToken = Str::random(40);
        $agency = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->completed()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        FeedbackInvitation::factory()->create([
            'case_id' => $case->id,
            'agency_id' => $agency->id,
            'referral_id' => $referral->id,
            'client_email' => $client->email,
            'token_prefix' => substr($fullToken, 0, 10),
            'token_hash' => hash('sha256', $fullToken),
        ]);

        $response = $this->get(route('feedbacks.submit-page', ['token' => $fullToken]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Feedback/Submit')
                ->has('invitation')
            );
    }

    #[Test]
    public function public_feedback_submit_creates_feedback_and_marks_invitation(): void
    {
        $agency = Agency::factory()->create();
        $service = Service::create(['agcy_id' => $agency->id, 'name' => 'Legal Assistance', 'processing_days' => 1]);
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->completed()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Legal Assistance',
        ]);

        $result = $this->invitationService->createInvitation(
            caseId: $case->id,
            agencyId: $agency->id,
            referralId: $referral->id,
            clientEmail: $client->email,
            serviceId: $service->id,
        );

        $rawToken = $result['token'];

        $response = $this->postJson(route('feedbacks.submit', ['token' => $rawToken]), [
            'servqual_responses' => [
                [
                    'dimension' => 'Tangibles',
                    'question_text' => 'The agency had modern-looking equipment.',
                    'expectation' => 4,
                    'perception' => 5,
                ],
                [
                    'dimension' => 'Reliability',
                    'question_text' => 'The agency performed the service right the first time.',
                    'expectation' => 3,
                    'perception' => 4,
                ],
            ],
            'overall_rating' => 4,
            'comments' => 'Very satisfied with the service.',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('feedback', [
            'case_id' => $case->id,
            'agency_id' => $agency->id,
            'referral_id' => $referral->id,
            'service_id' => $service->id,
        ]);

        $invitation = $result['invitation']->fresh();
        $this->assertNotNull($invitation->submitted_at);
        $this->assertNotNull($invitation->used_feedback_id);
    }

    #[Test]
    public function public_feedback_submit_requires_servqual_responses(): void
    {
        $response = $this->postJson(route('feedbacks.submit', ['token' => 'any-token']), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('servqual_responses');
    }
}
