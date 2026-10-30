<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Models\ReferralClientMessage;
use App\Models\ReferralClientMessageAttachment;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class ClientRequestVisibilityAuthorizationTest extends ReferralClientInboxTestCase
{
    public function test_review_transitions_and_hidden_visibility_block_other_agency(): void
    {
        $context = $this->attachmentContext();

        $this->actingAs($context['agencyUser'])->patch(route('referrals.client-requests.attachments.review', $context['attachment']), [
            'review_status' => 'REJECTED', 'visibility' => 'ALL',
        ])->assertRedirect();

        $this->assertSame('REJECTED', $context['attachment']->fresh()->review_status);
        $this->actingAs($context['otherAgencyUser'])->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $context['attachment']->id]))->assertForbidden();

        $this->actingAs($context['agencyUser'])->patch(route('referrals.client-requests.attachments.review', $context['attachment']), [
            'review_status' => 'ACCEPTED', 'visibility' => 'HIDDEN',
        ])->assertRedirect();

        $this->actingAs($context['otherAgencyUser'])->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $context['attachment']->id]))->assertForbidden();
    }

    public function test_all_visibility_allows_active_referred_agency(): void
    {
        $context = $this->attachmentContext();
        $this->actingAs($context['agencyUser'])->patch(route('referrals.client-requests.attachments.review', $context['attachment']), [
            'review_status' => 'ACCEPTED', 'visibility' => 'ALL',
        ])->assertRedirect();

        $this->actingAs($context['otherAgencyUser'])->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $context['attachment']->id]))->assertRedirect();
    }

    public function test_selected_visibility_allows_only_selected_agency(): void
    {
        $context = $this->attachmentContext();
        $this->actingAs($context['agencyUser'])->patch(route('referrals.client-requests.attachments.review', $context['attachment']), [
            'review_status' => 'ACCEPTED', 'visibility' => 'SELECTED', 'agency_ids' => [$context['otherAgency']->id],
        ])->assertRedirect();

        $this->actingAs($context['otherAgencyUser'])->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $context['attachment']->id]))->assertRedirect();
    }

    public function test_inactive_referral_denies_other_agency_even_when_selected(): void
    {
        $context = $this->attachmentContext();
        $context['otherReferral']->update(['status' => 'COMPLETED']);
        $this->actingAs($context['agencyUser'])->patch(route('referrals.client-requests.attachments.review', $context['attachment']), [
            'review_status' => 'ACCEPTED', 'visibility' => 'SELECTED', 'agency_ids' => [$context['otherAgency']->id],
        ])->assertRedirect();

        $this->actingAs($context['otherAgencyUser'])->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $context['attachment']->id]))->assertForbidden();
    }

    private function attachmentContext(): array
    {
        Config::set('filesystems.default', 'object-storage');
        Storage::fake('object-storage');
        $context = $this->context();
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
