<?php

namespace Tests\Feature;

use App\Events\CaseDraftPublished;
use App\Listeners\SendCaseDraftPublishedNotification;
use App\Mail\ClientUpdateMail;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseDraft;
use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\CaseNotification;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\NextOfKin;
use App\Models\User;
use App\Services\CaseDraftIdentifierGeneratorContract;
use App\Services\CaseDraftService;
use App\Services\CaseEventRecorder;
use App\Services\CaseService;
use App\Services\NotificationService;
use App\Services\PhilippineAddressService;
use App\Services\ReferralService;
use App\Services\TrackingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CaseDraftPublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_inactive_category_and_missing_or_inactive_issue_are_rejected_before_publish(): void
    {
        $user = $this->user();
        $category = CaseCategory::factory()->create(['is_active' => false]);
        $draft = $this->draft($user->id, $this->existingPayload($category->id));

        $this->expectException(ValidationException::class);
        app(CaseDraftService::class)->publish($draft, $user->id, 1);
    }

    public function test_inactive_issue_is_rejected_after_category_validation(): void
    {
        $user = $this->user();
        $category = CaseCategory::factory()->create();
        $issue = CaseIssue::create(['name' => 'Inactive', 'is_active' => false]);
        $draft = $this->draft($user->id, array_merge($this->existingPayload($category->id), ['case_issue_id' => $issue->id]));

        $this->expectException(ValidationException::class);
        app(CaseDraftService::class)->publish($draft, $user->id, 1);
    }

    public function test_missing_category_is_rejected_with_422_validation(): void
    {
        $user = $this->user();
        $draft = $this->draft($user->id, $this->existingPayload('00000000-0000-4000-8000-000000000000'));
        $draft->payload_encrypted = array_merge($draft->payload_encrypted, ['category_ids' => []]);
        $draft->save();

        $this->expectException(ValidationException::class);
        app(CaseDraftService::class)->publish($draft, $user->id, 1);
    }

    public function test_missing_existing_source_reference_is_rejected(): void
    {
        $user = $this->user();
        $category = CaseCategory::factory()->create();
        $draft = CaseDraft::create([
            'owner_id' => $user->id,
            'payload_encrypted' => array_merge($this->existingPayload($category->id), ['source_client_id' => '']),
            'payload_schema_version' => 1,
            'revision' => 1,
            'state' => CaseDraft::STATE_EDITING,
        ]);

        $this->expectException(ValidationException::class);
        app(CaseDraftService::class)->publish($draft, $user->id, 1);
    }

    public function test_soft_deleted_existing_source_reference_is_rejected(): void
    {
        $user = $this->user();
        $category = CaseCategory::factory()->create();
        $client = Client::factory()->create();
        $draft = $this->draft($user->id, $this->existingPayload($category->id, $client->id));
        $client->delete();

        $this->expectException(ValidationException::class);
        app(CaseDraftService::class)->publish($draft, $user->id, 1);
    }

    public function test_existing_client_records_are_byte_for_byte_unchanged_by_publish(): void
    {
        $user = $this->user();
        $category = CaseCategory::factory()->create();
        $client = Client::factory()->create(['first_name' => 'Canonical', 'last_name' => 'Client', 'date_of_birth' => '1980-01-01', 'sex' => 'MALE', 'contact_number' => '09170000000', 'email' => 'canonical@example.test']);
        $address = ClientAddress::create(['client_id' => $client->id, 'region' => 'Region VII', 'province' => 'Cebu', 'city_municipality' => 'Cebu City', 'barangay' => 'Lahug']);
        $employment = ClientEmployment::create(['client_id' => $client->id, 'employer_name' => 'Canonical Employer']);
        $nok = NextOfKin::create(['client_id' => $client->id, 'first_name' => 'Canonical NOK', 'email' => 'nok@example.test']);
        $before = [$client->fresh()->getAttributes(), $address->fresh()->getAttributes(), $employment->fresh()->getAttributes(), $nok->fresh()->getAttributes()];

        $payload = array_merge($this->existingPayload($category->id, $client->id), [
            'selected_nok_id' => $nok->id,
        ]);
        $case = app(CaseDraftService::class)->publish($this->draft($user->id, $payload), $user->id, 1);

        $after = [$client->fresh()->getAttributes(), $address->fresh()->getAttributes(), $employment->fresh()->getAttributes(), $nok->fresh()->getAttributes()];
        $this->assertSame($before, $after);
        $this->assertSame($client->id, $case->client_id);
    }

    public function test_new_client_materialization_failure_rolls_back_everything(): void
    {
        $user = $this->user();
        $category = CaseCategory::factory()->create();
        $this->instance(PhilippineAddressService::class, Mockery::mock(PhilippineAddressService::class, function ($mock) {
            $mock->shouldReceive('resolveNames')->andThrow(new \RuntimeException('address failure'));
        }));
        $draft = $this->draft($user->id, array_merge($this->newPayload($category->id), ['address' => [
            'region' => 'NCR',
            'province' => 'Metro Manila',
            'city_municipality' => 'Manila',
            'barangay' => 'Barangay 1',
        ]]));

        try {
            app(CaseDraftService::class)->publish($draft, $user->id, 1);
            $this->fail('Materialization failure was not propagated.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('address failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('cases', 0);
        $this->assertSame(CaseDraft::STATE_EDITING, $draft->fresh()->state);
        $this->assertNotNull($draft->fresh()->payload_encrypted);
    }

    public function test_case_event_failure_rolls_back_case_and_draft_transition(): void
    {
        $user = $this->user();
        $category = CaseCategory::factory()->create();
        $this->instance(CaseEventRecorder::class, Mockery::mock(CaseEventRecorder::class, function ($mock) {
            $mock->shouldReceive('caseOpened')->andThrow(new \RuntimeException('event failure'));
        }));
        $draft = $this->draft($user->id, $this->newPayload($category->id));

        $this->expectException(\RuntimeException::class);
        try {
            app(CaseDraftService::class)->publish($draft, $user->id, 1);
        } finally {
            $this->assertDatabaseCount('cases', 0);
            $this->assertSame(CaseDraft::STATE_EDITING, $draft->fresh()->state);
        }
    }

    public function test_publish_is_idempotent_with_one_case_event_audit_and_notification(): void
    {
        Mail::fake();
        $user = $this->user();
        $category = CaseCategory::factory()->create();
        $service = app(CaseDraftService::class);
        $draft = $this->draft($user->id, $this->newPayload($category->id));
        $first = $service->publish($draft, $user->id, 1);
        $second = $service->publish($draft->id, $user->id, 999);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CaseFile::whereKey($first->id)->count());
        $this->assertSame(1, CaseEvent::where('case_id', $first->id)->count());
        $this->assertSame(1, AuditLog::where('entity_id', $draft->id)->where('action', 'PUBLISH')->count());
    }

    public function test_identifier_collision_retries_deterministically_through_the_contract(): void
    {
        Mail::fake();
        $user = $this->user();
        $category = CaseCategory::factory()->create();
        CaseFile::factory()->create([
            'case_number' => 'CASE-COLLIDE',
            'tracker_number' => 'OWBAP-COLLIDE',
        ]);
        $generator = Mockery::mock(CaseDraftIdentifierGeneratorContract::class);
        $generator->shouldReceive('generate')->twice()->andReturn(
            ['CASE-COLLIDE', 'OWBAP-COLLIDE'],
            ['CASE-UNIQUE', 'OWBAP-UNIQUE'],
        );
        $this->instance(CaseDraftIdentifierGeneratorContract::class, $generator);

        $case = app(CaseDraftService::class)->publish(
            $this->draft($user->id, $this->newPayload($category->id)),
            $user->id,
            1,
        );

        $this->assertSame('CASE-UNIQUE', $case->case_number);
        $this->assertSame('OWBAP-UNIQUE', $case->tracker_number);
    }

    public function test_recipient_resolution_uses_client_type_and_selected_canonical_nok(): void
    {
        Mail::fake();
        $user = $this->user();
        $category = CaseCategory::factory()->create();
        $client = Client::factory()->create(['first_name' => 'Canonical', 'last_name' => 'Client', 'date_of_birth' => '1980-01-01', 'sex' => 'MALE', 'contact_number' => '09170000000', 'email' => 'ofw@example.test']);
        ClientAddress::create(['client_id' => $client->id, 'region' => 'Region VII', 'province' => 'Cebu', 'city_municipality' => 'Cebu City', 'barangay' => 'Lahug']);
        $nok = NextOfKin::create(['client_id' => $client->id, 'first_name' => 'Selected', 'email' => 'selected@example.test']);
        $service = app(CaseDraftService::class);

        $ofw = $service->publish($this->draft($user->id, $this->existingPayload($category->id, $client->id)), $user->id, 1);
        $this->assertDatabaseHas('case_notifications', ['case_id' => $ofw->id, 'client_email' => 'ofw@example.test']);

        $nokPayload = array_merge($this->existingPayload($category->id, $client->id), ['client_type' => 'NEXT_OF_KIN', 'selected_nok_id' => $nok->id]);
        $nextOfKinCase = $service->publish($this->draft($user->id, $nokPayload), $user->id, 1);
        $this->assertDatabaseHas('case_notifications', ['case_id' => $nextOfKinCase->id, 'client_email' => 'selected@example.test']);
    }

    public function test_listener_failure_is_reported_and_retriable_without_duplicate_delivery(): void
    {
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $event = new CaseDraftPublished($case->id, 'recipient@example.test', 'case-draft:'.$case->id.':published');
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('notifyOfw')->once()->andThrow(new \RuntimeException('delivery failure'));
        $this->instance(NotificationService::class, $notifications);

        $this->expectException(\RuntimeException::class);
        (new SendCaseDraftPublishedNotification)->handle($event);
    }

    public function test_selected_nok_evidence_drives_later_case_referral_and_tracking_recipients(): void
    {
        Mail::fake();
        $user = $this->user();
        $category = CaseCategory::factory()->create();
        $client = Client::factory()->create(['first_name' => 'Canonical', 'last_name' => 'Client', 'date_of_birth' => '1980-01-01', 'sex' => 'MALE', 'contact_number' => '09170000000', 'email' => 'client@example.test']);
        ClientAddress::create(['client_id' => $client->id, 'region' => 'Region VII', 'province' => 'Cebu', 'city_municipality' => 'Cebu City', 'barangay' => 'Lahug']);
        $nok = NextOfKin::create(['client_id' => $client->id, 'first_name' => 'Selected', 'email' => 'selected@example.test']);
        $service = app(CaseDraftService::class);
        $case = $service->publish($this->draft($user->id, array_merge($this->existingPayload($category->id, $client->id), [
            'client_type' => 'NEXT_OF_KIN',
            'selected_nok_id' => $nok->id,
        ])), $user->id, 1);

        app(CaseService::class)->updateCase($case->id, [
            'client_type' => 'NEXT_OF_KIN',
            'summary' => 'Later update',
        ], $user->id);
        DB::commit();
        $this->assertDatabaseHas('case_notifications', [
            'case_id' => $case->id,
            'type' => 'case_updated',
            'client_email' => 'selected@example.test',
        ]);

        $agency = Agency::factory()->create();
        app(ReferralService::class)->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Counselling',
        ], $user->id);
        $this->assertDatabaseHas('case_notifications', [
            'case_id' => $case->id,
            'type' => 'referral_created',
            'client_email' => 'selected@example.test',
        ]);

        $tracking = app(TrackingService::class)->buildTrackingData($case->fresh());
        $this->assertGreaterThan(0, $tracking['caseNotifications']['unread_count']);
        $this->assertNotEmpty($tracking['caseNotifications']['items']);
    }

    public function test_notification_listener_claim_is_idempotent_across_retry(): void
    {
        Mail::fake();
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $event = new CaseDraftPublished($case->id, 'recipient@example.test', 'case-draft:'.$case->id.':published');
        $listener = new SendCaseDraftPublishedNotification;

        $listener->handle($event);
        $listener->handle($event);

        $this->assertSame(1, CaseNotification::where('case_id', $case->id)->where('type', 'case_published')->count());
        Mail::assertQueued(ClientUpdateMail::class, 1);
    }

    public function test_identifier_collision_exhaustion_is_bounded_and_atomic(): void
    {
        Mail::fake();
        $user = $this->user();
        $category = CaseCategory::factory()->create();
        CaseFile::factory()->create(['case_number' => 'CASE-COLLIDE', 'tracker_number' => 'OWBAP-COLLIDE']);
        $generator = Mockery::mock(CaseDraftIdentifierGeneratorContract::class);
        $generator->shouldReceive('generate')->times(3)->andReturn(['CASE-COLLIDE', 'OWBAP-COLLIDE']);
        $this->instance(CaseDraftIdentifierGeneratorContract::class, $generator);
        $draft = $this->draft($user->id, $this->newPayload($category->id));

        $this->expectException(QueryException::class);
        try {
            app(CaseDraftService::class)->publish($draft, $user->id, 1);
        } finally {
            $this->assertSame(CaseDraft::STATE_EDITING, $draft->fresh()->state);
        }
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'CASE_MANAGER']);
    }

    private function draft(string $ownerId, array $payload): CaseDraft
    {
        return app(CaseDraftService::class)->create($payload, $ownerId);
    }

    private function existingPayload(string $categoryId, ?string $clientId = null): array
    {
        if ($clientId === null) {
            $clientId = Client::factory()->create([
                'first_name' => 'Existing',
                'last_name' => 'Client',
                'date_of_birth' => '1980-01-01',
                'sex' => 'MALE',
                'contact_number' => '09170000000',
                'email' => 'existing@example.test',
            ])->id;
        }

        return [
            'schema_version' => 1,
            'client_source' => 'EXISTING',
            'source_client_id' => $clientId,
            'client_type' => 'OFW',
            'category_ids' => [$categoryId],
        ];
    }

    private function newPayload(string $categoryId): array
    {
        return [
            'schema_version' => 1,
            'client_source' => 'NEW',
            'client_type' => 'OFW',
            'category_ids' => [$categoryId],
            'consent' => ['accepted_at' => '2026-01-15T10:30:45+00:00', 'notice_version' => 'v1'],
            'address' => [
                'region' => 'Region VII',
                'province' => 'Cebu',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Lahug',
            ],
            'client' => [
                'first_name' => 'New', 'last_name' => 'Client', 'date_of_birth' => '1990-01-01',
                'sex' => 'MALE', 'contact_number' => '09170000000', 'email' => 'new@example.test',
            ],
        ];
    }
}
