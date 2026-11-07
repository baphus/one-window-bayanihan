<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Models\ReferralClientMessage;
use App\Models\ReferralClientMessageAttachment;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class ClientRequestVisibilityAuthorizationTest extends ReferralClientInboxTestCase
{
    public function test_review_transitions_and_hidden_visibility_block_other_agency(): void
    {
        $context = $this->attachmentContext();

        $this->actingAsWithMfa($context['agencyUser'])->patch(route('referrals.client-requests.attachments.review', $context['attachment']), [
            'review_status' => 'REJECTED', 'visibility' => 'ALL',
        ])->assertRedirect();

        $this->assertSame('REJECTED', $context['attachment']->fresh()->review_status);
        $this->actingAsWithMfa($context['otherAgencyUser'])->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $context['attachment']->id]))->assertForbidden();

        $this->actingAsWithMfa($context['agencyUser'])->patch(route('referrals.client-requests.attachments.review', $context['attachment']), [
            'review_status' => 'ACCEPTED', 'visibility' => 'HIDDEN',
        ])->assertRedirect();

        $this->actingAsWithMfa($context['otherAgencyUser'])->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $context['attachment']->id]))->assertForbidden();
    }

    public function test_all_visibility_allows_active_referred_agency(): void
    {
        $context = $this->attachmentContext();
        $this->actingAsWithMfa($context['agencyUser'])->patch(route('referrals.client-requests.attachments.review', $context['attachment']), [
            'review_status' => 'ACCEPTED', 'visibility' => 'ALL',
        ])->assertRedirect();

        $this->actingAsWithMfa($context['otherAgencyUser'])->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $context['attachment']->id]))->assertRedirect();
    }

    public function test_selected_visibility_allows_only_selected_agency(): void
    {
        $context = $this->attachmentContext();
        $this->actingAsWithMfa($context['agencyUser'])->patch(route('referrals.client-requests.attachments.review', $context['attachment']), [
            'review_status' => 'ACCEPTED', 'visibility' => 'SELECTED', 'agency_ids' => [$context['otherAgency']->id],
        ])->assertRedirect();

        $this->actingAsWithMfa($context['otherAgencyUser'])->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $context['attachment']->id]))->assertRedirect();

        $unselectedAgencyUser = $context['otherAgencyUser']->replicate();
        $unselectedAgencyUser->id = (string) str()->uuid();
        $unselectedAgencyUser->email = 'unselected@example.test';
        $unselectedAgencyUser->agcy_id = $context['thirdAgency']->id;
        $unselectedAgencyUser->save();
        $this->actingAsWithMfa($unselectedAgencyUser)->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $context['attachment']->id]))->assertForbidden();
    }

    public function test_inactive_referral_denies_other_agency_even_when_selected(): void
    {
        $context = $this->attachmentContext();
        $context['otherReferral']->update(['status' => 'COMPLETED']);
        $this->actingAsWithMfa($context['agencyUser'])->patch(route('referrals.client-requests.attachments.review', $context['attachment']), [
            'review_status' => 'ACCEPTED', 'visibility' => 'SELECTED', 'agency_ids' => [$context['otherAgency']->id],
        ])->assertRedirect();

        $this->actingAsWithMfa($context['otherAgencyUser'])->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $context['attachment']->id]))->assertForbidden();
    }

    public function test_admin_can_download_request_attachment_without_agency_sharing(): void
    {
        $context = $this->attachmentContext();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->mock(StorageService::class)->shouldReceive('temporaryUrl')
            ->once()->with($context['attachment']->file_path, 24)->andReturn('https://storage.example.test/document.pdf');

        $this->actingAsWithMfa($admin)
            ->get(route('referrals.client-requests.attachments.download', [$context['referral'], $context['attachment']]))
            ->assertRedirect('https://storage.example.test/document.pdf');
    }

    public static function guestAttachmentStates(): array
    {
        return [
            'pending agency file' => ['AGENCY_USER', 'PENDING', 'ALL', false, false, false],
            'rejected agency file' => ['AGENCY_USER', 'REJECTED', 'ALL', false, false, false],
            'hidden agency file' => ['AGENCY_USER', 'ACCEPTED', 'HIDDEN', false, false, false],
            'selected agency file' => ['AGENCY_USER', 'ACCEPTED', 'SELECTED', false, false, false],
            'approved public agency file' => ['AGENCY_USER', 'ACCEPTED', 'ALL', false, false, true],
            'client upload' => ['CLIENT_ACCESS', 'PENDING', 'HIDDEN', false, false, true],
            'deleted attachment' => ['CLIENT_ACCESS', 'PENDING', 'HIDDEN', true, false, false],
            'deleted message' => ['CLIENT_ACCESS', 'PENDING', 'HIDDEN', false, true, false],
        ];
    }

    #[DataProvider('guestAttachmentStates')]
    public function test_guest_projection_and_download_follow_client_visibility(string $sender, string $review, string $visibility, bool $deletedAttachment, bool $deletedMessage, bool $allowed): void
    {
        $context = $this->attachmentContext();
        $issued = $this->issue($context);
        $context['message']->forceFill([
            'sender_kind' => $sender,
            'user_id' => $sender === 'CLIENT_ACCESS' ? null : $context['agencyUser']->id,
            'access_link_id' => $sender === 'CLIENT_ACCESS' ? $issued['link']->id : null,
            'kind' => 'MESSAGE',
            'is_deleted' => $deletedMessage,
        ])->save();
        $context['attachment']->forceFill(['review_status' => $review, 'visibility' => $visibility, 'is_deleted' => $deletedAttachment])->save();
        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']])->assertRedirect();

        $this->get(route('track.request.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Tracking/Show')
            ->has('clientRequestPanel.activeRequest.messages', $deletedMessage ? 0 : 1)
            ->when(! $deletedMessage, fn (Assert $page) => $page
                ->has('clientRequestPanel.activeRequest.messages.0.attachments', $allowed ? 1 : 0)));

        if ($allowed) {
            $this->mock(StorageService::class)->shouldReceive('temporaryUrl')->once()
                ->with($context['attachment']->file_path, 24)->andReturn('https://storage.example.test/document.pdf');
        } else {
            $this->mock(StorageService::class)->shouldNotReceive('temporaryUrl');
        }

        $response = $this->get(route('track.request.attachments.download', $context['attachment']));
        if ($allowed) {
            $response->assertRedirect('https://storage.example.test/document.pdf');
        } else {
            $response->assertNotFound();
        }
    }

    private function attachmentContext(): array
    {
        Config::set('filesystems.default', 'object-storage');
        Storage::fake('object-storage');
        $context = $this->context();
        $thirdAgency = $context['otherAgency']->replicate();
        $thirdAgency->id = (string) str()->uuid();
        $thirdAgency->name = 'Third Agency';
        $thirdAgency->short = 'THIRD';
        $thirdAgency->slug = 'third-agency';
        $thirdAgency->save();
        $context['thirdAgency'] = $thirdAgency;

        $otherReferral = $context['referral']->replicate();
        $otherReferral->id = (string) str()->uuid();
        $otherReferral->agcy_id = $context['otherAgency']->id;
        $otherReferral->save();
        $message = ReferralClientMessage::factory()->create(['request_id' => $context['clientRequest']->id]);
        $attachment = ReferralClientMessageAttachment::create([
            'message_id' => $message->id,
            'file_name' => 'document.pdf',
            'file_path' => 'client-request-attachments/document.pdf',
            'file_type' => 'application/pdf',
            'size' => 10,
            'review_status' => 'PENDING',
            'visibility' => 'HIDDEN',
        ]);

        return [...$context, 'otherReferral' => $otherReferral, 'message' => $message, 'attachment' => $attachment];
    }
}
