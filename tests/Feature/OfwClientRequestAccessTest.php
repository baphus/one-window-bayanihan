<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientMessageAttachment;
use App\Models\ReferralClientRequest;
use App\Models\ReferralClientRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    public function checklist_links_latest_replacement_upload(): void
    {
        [$user, $case, $referral, $request] = $this->context();
        $item = ReferralClientRequestItem::create(['request_id' => $request->id, 'label' => 'Passport', 'sort_order' => 0]);
        $oldMessage = ReferralClientMessage::factory()->create(['request_id' => $request->id, 'sender_kind' => ReferralClientMessage::SENDER_CLIENT_USER, 'user_id' => $user->id]);
        $oldAttachment = ReferralClientMessageAttachment::create(['message_id' => $oldMessage->id, 'request_item_id' => $item->id, 'file_name' => 'old.pdf', 'file_path' => 'old.pdf', 'file_type' => 'application/pdf', 'size' => 1]);
        $newMessage = ReferralClientMessage::factory()->create(['request_id' => $request->id, 'sender_kind' => ReferralClientMessage::SENDER_CLIENT_USER, 'user_id' => $user->id]);
        $newAttachment = ReferralClientMessageAttachment::create(['message_id' => $newMessage->id, 'request_item_id' => $item->id, 'file_name' => 'new.pdf', 'file_path' => 'new.pdf', 'file_type' => 'application/pdf', 'size' => 1]);
        $oldAttachment->delete();

        $response = $this->actingAsWithMfa($user)->withHeader('X-Inertia', 'true')->get(route('ofw.case.requests.show', ['case' => $case, 'referral' => $referral, 'request' => $request]));
        $response->assertOk()
            ->assertJsonPath('props.clientRequestPanel.activeRequest.checklist.0.attachment.file_name', 'new.pdf');
        $this->assertSame($item->id, $response->json('props.clientRequestPanel.activeRequest.messages.1.attachments.0.request_item_id'));
    }

    #[Test]
    public function request_uploads_from_closed_requests_remain_in_owner_documents_and_exclude_staff_files(): void
    {
        [$user, $case, $referral, $request] = $this->context(['status' => ReferralClientRequest::STATUS_COMPLETED]);
        $message = ReferralClientMessage::factory()->create(['request_id' => $request->id, 'sender_kind' => ReferralClientMessage::SENDER_CLIENT_USER, 'user_id' => $user->id]);
        $attachment = ReferralClientMessageAttachment::create(['message_id' => $message->id, 'file_name' => 'old.pdf', 'file_path' => 'old.pdf', 'file_type' => 'application/pdf', 'size' => 1]);
        $agencyMessage = ReferralClientMessage::factory()->create(['request_id' => $request->id, 'sender_kind' => ReferralClientMessage::SENDER_AGENCY_USER, 'user_id' => User::factory()->create(['role' => UserRole::AGENCY->value])->id]);
        ReferralClientMessageAttachment::create(['message_id' => $agencyMessage->id, 'file_name' => 'internal.pdf', 'file_path' => 'internal.pdf', 'file_type' => 'application/pdf', 'size' => 1, 'review_status' => 'PENDING', 'visibility' => 'HIDDEN']);

        $response = $this->actingAsWithMfa($user)->withHeader('X-Inertia', 'true')->get('/my-cases/'.$case->id);
        $response->assertOk();
        $this->assertSame([$attachment->id], collect($response->json('props.requestDocuments'))->pluck('id')->all());
        $this->assertSame(route('ofw.case.requests.attachments.download', ['case' => $case, 'referral' => $referral, 'request' => $request, 'attachment' => $attachment]), $response->json('props.requestDocuments.0.download_url'));
        $response->assertJsonMissing(['name' => 'internal.pdf']);
    }

    #[Test]
    public function owner_can_view_request_and_response_actions_without_access_link_data(): void
    {
        [$user, $case, $referral, $request] = $this->context();

        $response = $this->actingAsWithMfa($user)->withHeader('X-Inertia', 'true')->get(route('ofw.case.requests.show', [
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
    public function owner_can_upload_checklist_document_with_reply(): void
    {
        config(['filesystems.default' => 'object-storage']);
        Storage::fake('object-storage');
        [$user, $case, $referral, $request] = $this->context();
        $item = ReferralClientRequestItem::create(['request_id' => $request->id, 'label' => 'Passport', 'sort_order' => 0]);
        $file = UploadedFile::fake()->createWithContent('passport.pdf', '%PDF-1.4 client document', 'application/pdf');

        $this->actingAsWithMfa($user)->post(route('ofw.case.requests.messages.store', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
        ]), [
            'body' => 'Here is my passport.',
            'attachments' => [$file],
            'request_item_ids' => [$item->id],
        ])->assertRedirect(route('ofw.case.requests.show', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
        ]));

        $message = ReferralClientMessage::where('request_id', $request->id)->firstOrFail();
        $this->assertSame(ReferralClientMessage::SENDER_CLIENT_USER, $message->sender_kind);
        $this->assertSame($user->id, $message->user_id);
        $this->assertSame('Here is my passport.', $message->body);
        $attachment = $message->attachments()->firstOrFail();
        $this->assertSame($item->id, $attachment->request_item_id);
        Storage::disk('object-storage')->assertExists($attachment->file_path);
    }

    #[Test]
    public function another_client_cannot_upload_to_request(): void
    {
        config(['filesystems.default' => 'object-storage']);
        Storage::fake('object-storage');
        [, $case, $referral, $request] = $this->context();
        $otherUser = User::factory()->create(['role' => UserRole::OFW->value]);
        $file = UploadedFile::fake()->createWithContent('passport.pdf', '%PDF-1.4 client document', 'application/pdf');

        $this->actingAsWithMfa($otherUser)->post(route('ofw.case.requests.messages.store', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
        ]), ['body' => 'Not mine', 'attachments' => [$file]])->assertForbidden();

        $this->assertDatabaseCount('referral_client_messages', 0);
        $this->assertDatabaseCount('referral_client_message_attachments', 0);
        Storage::disk('object-storage')->assertDirectoryEmpty('client-request-attachments');
    }

    #[Test]
    public function owner_reply_is_stored_as_client_user_without_an_access_link(): void
    {
        [$user, $case, $referral, $request] = $this->context();

        $this->actingAsWithMfa($user)->post(route('ofw.case.requests.messages.store', [
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
        $otherUser = User::factory()->create(['role' => UserRole::OFW->value]);

        $this->actingAsWithMfa($otherUser)->post(route('ofw.case.requests.messages.store', [
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
        $otherUser = User::factory()->create(['role' => UserRole::OFW->value]);

        $this->actingAsWithMfa($otherUser)->get(route('ofw.case.requests.show', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
        ]))->assertForbidden();
    }

    #[Test]
    public function mismatched_referral_and_request_are_not_found(): void
    {
        [$user, $case, $referral, $request] = $this->context();
        $otherCase = CaseFile::factory()->open()->create(['ofw_user_id' => $user->id]);
        $otherReferral = Referral::factory()->create(['case_id' => $otherCase->id]);

        $this->actingAsWithMfa($user)->get(route('ofw.case.requests.show', [
            'case' => $case,
            'referral' => $otherReferral,
            'request' => $request,
        ]))->assertNotFound();

        $otherRequest = ReferralClientRequest::factory()->create(['referral_id' => $otherReferral->id]);
        $this->actingAsWithMfa($user)->get(route('ofw.case.requests.show', [
            'case' => $case,
            'referral' => $referral,
            'request' => $otherRequest,
        ]))->assertNotFound();
    }

    /**
     * A request that closed while the page was open must read as gone, not as
     * a bad upload: the service's LogicException used to surface as a
     * validation error on the attachments field.
     */
    #[Test]
    public function terminal_request_returns_not_found_rather_than_a_validation_error(): void
    {
        [$user, $case, $referral, $request] = $this->context(['status' => ReferralClientRequest::STATUS_COMPLETED]);

        $this->actingAsWithMfa($user)->post(route('ofw.case.requests.messages.store', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
        ]), ['body' => 'A reply'])
            ->assertNotFound()
            ->assertSessionHasNoErrors();

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

        $this->actingAsWithMfa($user)->get(route('ofw.case.requests.attachments.download', [
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

        $this->actingAsWithMfa($user)->get(route('ofw.case.requests.attachments.download', [
            'case' => $case,
            'referral' => $referral,
            'request' => $request,
            'attachment' => $attachment,
        ]))->assertNotFound();
    }

    private function context(array $requestOverrides = []): array
    {
        $user = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->open()->create(['ofw_user_id' => $user->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $request = ReferralClientRequest::factory()->create(['referral_id' => $referral->id, ...$requestOverrides]);

        return [$user, $case, $referral, $request];
    }
}
