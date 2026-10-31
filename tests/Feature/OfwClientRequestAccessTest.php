<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientMessageAttachment;
use App\Models\ReferralClientRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfwClientRequestAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    #[Test]
    public function owner_can_view_request_and_response_actions_without_access_link_data(): void
    {
        [$user, $case, $referral, $request] = $this->context();

        $response = $this->actingAs($user)->withHeader('X-Inertia', 'true')->get(route('ofw.case.requests.show', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
        ]));

        $response->assertOk()
            ->assertJsonPath('component', 'Tracking/Show')
            ->assertJsonPath('props.clientRequestPanel.activeRequest.id', $request->id)
            ->assertJsonPath('props.clientRequestPanel.actions.reply', route('ofw.case.requests.messages.store', [
                'case' => $case,
                'referral' => $referral,
                'request' => $request,
            ]))
            ->assertJsonMissingPath('props.clientRequestPanel.activeRequest.accessLinks')
            ->assertDontSee('token_hash');
    }

    #[Test]
    public function owner_reply_is_stored_as_client_user_without_an_access_link(): void
    {
        [$user, $case, $referral, $request] = $this->context();

        $this->actingAs($user)->post(route('ofw.case.requests.messages.store', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
        ]), ['body' => 'Here is my reply'])->assertRedirect(route('ofw.case.requests.show', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
        ]));

        $this->assertDatabaseHas('referral_client_messages', [
            'request_id' => $request->id,
            'sender_kind' => ReferralClientMessage::SENDER_CLIENT_USER,
            'user_id' => $user->id,
            'access_link_id' => null,
        ]);
        $this->assertDatabaseCount('referral_client_access_links', 0);
        $this->assertDatabaseHas('referral_client_requests', [
            'id' => $request->id,
            'status' => ReferralClientRequest::STATUS_CLIENT_RESPONDED,
        ]);
    }

    #[Test]
    public function another_client_cannot_reply_to_request(): void
    {
        [, $case, $referral, $request] = $this->context();
        $otherUser = User::factory()->create(['role' => UserRole::OFW->value, 'client_id' => Client::factory()->create()->id]);

        $this->actingAs($otherUser)->post(route('ofw.case.requests.messages.store', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
        ]), ['body' => 'Not mine'])->assertForbidden();

        $this->assertDatabaseCount('referral_client_messages', 0);
    }

    #[Test]
    public function another_client_cannot_view_request(): void
    {
        [, $case, $referral, $request] = $this->context();
        $otherUser = User::factory()->create(['role' => UserRole::OFW->value, 'client_id' => Client::factory()->create()->id]);

        $this->actingAs($otherUser)->get(route('ofw.case.requests.show', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
        ]))->assertForbidden();
    }

    #[Test]
    public function mismatched_referral_and_request_are_not_found(): void
    {
        [$user, $case, $referral, $request] = $this->context();
        $otherCase = CaseFile::factory()->open()->create(['client_id' => $user->client_id]);
        $otherReferral = Referral::factory()->create(['case_id' => $otherCase->id]);

        $this->actingAs($user)->get(route('ofw.case.requests.show', [
            'case' => $case,
            'referral' => $otherReferral,
            'request' => $request,
        ]))->assertNotFound();

        $otherRequest = ReferralClientRequest::factory()->create(['referral_id' => $otherReferral->id]);
        $this->actingAs($user)->get(route('ofw.case.requests.show', [
            'case' => $case,
            'referral' => $referral,
            'request' => $otherRequest,
        ]))->assertNotFound();
    }

    #[Test]
    public function terminal_request_rejects_owner_submission(): void
    {
        [$user, $case, $referral, $request] = $this->context(['status' => ReferralClientRequest::STATUS_COMPLETED]);

        $this->actingAs($user)->post(route('ofw.case.requests.messages.store', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
        ]), ['body' => 'A reply'])->assertSessionHasErrors('attachments');

        $this->assertDatabaseMissing('referral_client_messages', ['request_id' => $request->id]);
    }

    #[Test]
    public function attachment_download_must_match_request_in_url_and_be_client_visible(): void
    {
        [$user, $case, $referral, $request] = $this->context();
        $otherRequest = ReferralClientRequest::factory()->create(['referral_id' => $referral->id]);
        $message = ReferralClientMessage::factory()->create([
            'request_id' => $request->id,
            'sender_kind' => ReferralClientMessage::SENDER_AGENCY_USER,
            'user_id' => $user->id,
        ]);
        $attachment = ReferralClientMessageAttachment::create([
            'message_id' => $message->id,
            'file_name' => 'document.pdf',
            'file_path' => 'client-request-attachments/file.pdf',
            'file_type' => 'application/pdf',
            'size' => 12,
            'review_status' => 'PENDING',
            'visibility' => 'HIDDEN',
        ]);

        $this->actingAs($user)->get(route('ofw.case.requests.attachments.download', [
            'case' => $case,
            'referral' => $referral,
            'request' => $otherRequest,
            'attachment' => $attachment,
        ]))->assertNotFound();
    }

    #[Test]
    public function owner_cannot_download_agency_attachment_until_it_is_client_visible(): void
    {
        [$user, $case, $referral, $request] = $this->context();
        $message = ReferralClientMessage::factory()->create([
            'request_id' => $request->id,
            'sender_kind' => ReferralClientMessage::SENDER_AGENCY_USER,
            'user_id' => User::factory()->create(['role' => UserRole::AGENCY->value])->id,
        ]);
        $attachment = ReferralClientMessageAttachment::create([
            'message_id' => $message->id,
            'file_name' => 'internal.pdf',
            'file_path' => 'client-request-attachments/internal.pdf',
            'file_type' => 'application/pdf',
            'size' => 12,
            'review_status' => 'PENDING',
            'visibility' => 'HIDDEN',
        ]);

        $this->actingAs($user)->get(route('ofw.case.requests.attachments.download', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
            'attachment' => $attachment,
        ]))->assertNotFound();
    }

    private function context(array $requestOverrides = []): array
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['role' => UserRole::OFW->value, 'client_id' => $client->id]);
        $case = CaseFile::factory()->open()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $request = ReferralClientRequest::factory()->create(['referral_id' => $referral->id, ...$requestOverrides]);

        return [$user, $case, $referral, $request];
    }
}
