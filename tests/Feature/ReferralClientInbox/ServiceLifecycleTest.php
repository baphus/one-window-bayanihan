<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Models\Milestone;
use App\Models\ReferralClientRequest;
use App\Models\User;
use App\Services\ReferralClientRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;

class ServiceLifecycleTest extends ReferralClientInboxTestCase
{
    public function test_document_request_requires_checklist_and_creates_linked_milestone(): void
    {
        $context = $this->context();
        $service = app(ReferralClientRequestService::class);

        try {
            $service->createRequest($context['referral'], $context['agencyUser'], [
                'type' => ReferralClientRequest::TYPE_DOCUMENT_REQUEST,
                'title' => 'Documents',
                'instructions' => 'Please provide documents.',
                'items' => [],
            ]);
            $this->fail('A document request without a checklist should be rejected.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $request = $service->createRequest($context['referral'], $context['agencyUser'], [
            'type' => ReferralClientRequest::TYPE_DOCUMENT_REQUEST,
            'title' => 'Documents',
            'instructions' => 'Please provide documents.',
            'items' => ['Passport', 'Contract'],
        ]);

        $this->assertSame('PENDING', $context['referral']->fresh()->status);
        $this->assertSame(['Passport', 'Contract'], $request->items->pluck('label')->all());
        $this->assertSame(['Passport', 'Contract'], $request->milestone->requirements);
        $this->assertSame($request->id, Milestone::where('client_request_id', $request->id)->value('client_request_id'));
    }

    public function test_lifecycle_transitions_complete_cancel_and_reopen(): void
    {
        $context = $this->context();
        $service = app(ReferralClientRequestService::class);

        $completed = $service->complete($context['clientRequest'], $context['agencyUser']);
        $this->assertSame(ReferralClientRequest::STATUS_COMPLETED, $completed->status);

        $reopened = $service->reopen($completed, $context['agencyUser']);
        $this->assertSame(ReferralClientRequest::STATUS_OPEN, $reopened->status);

        $cancelled = $service->cancel($reopened, $context['agencyUser']);
        $this->assertSame(ReferralClientRequest::STATUS_CANCELLED, $cancelled->status);
    }

    public function test_terminal_referral_or_case_blocks_client_facing_writes(): void
    {
        $context = $this->context();
        $service = app(ReferralClientRequestService::class);

        $context['referral']->update(['status' => 'COMPLETED']);
        try {
            $service->sendAgencyMessage($context['clientRequest'], $context['agencyUser'], 'No longer allowed.');
            $this->fail('Completed referrals should reject client-facing writes.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $context['referral']->update(['status' => 'PENDING']);
        $context['case']->update(['status' => 'CLOSED', 'closed_at' => now()]);
        $this->expectException(LogicException::class);
        $service->sendAgencyMessage($context['clientRequest'], $context['agencyUser'], 'No longer allowed.');
    }

    public function test_other_agency_and_non_owner_case_manager_are_denied(): void
    {
        $context = $this->context();
        $service = app(ReferralClientRequestService::class);
        $otherManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        try {
            $service->createRequest($context['referral'], $context['otherAgencyUser'], [
                'type' => ReferralClientRequest::TYPE_QUESTION,
                'title' => 'No access',
                'instructions' => 'No access',
            ]);
            $this->fail('Other agency should be denied.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->expectException(AuthorizationException::class);
        $service->assertCanRead($context['referral'], $otherManager);
    }
}
