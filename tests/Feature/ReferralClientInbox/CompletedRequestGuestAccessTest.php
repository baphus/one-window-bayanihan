<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Models\ReferralClientRequest;
use App\Services\ReferralClientRequestService;

class CompletedRequestGuestAccessTest extends ReferralClientInboxTestCase
{
    public function test_client_can_view_completed_request_until_due_date(): void
    {
        $context = $this->context();
        $context['clientRequest']->update(['due_at' => now()->addDays(14)]);
        $issued = $this->issue($context);

        app(ReferralClientRequestService::class)->complete(
            $context['clientRequest']->fresh(),
            $context['agencyUser'],
        );

        // The link survives completion and its expiry extends through the due date.
        $link = $issued['link']->fresh();
        $this->assertNull($link->revoked_at);
        $this->assertTrue($link->expires_at->isAfter(now()->addDays(13)));

        // The client can still open the request and sees its completed status.
        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']])
            ->assertRedirect(route('track.request.index'));

        $this->get(route('track.request.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Tracking/Show')
                ->where('clientRequestPanel.activeRequest.status', ReferralClientRequest::STATUS_COMPLETED)
                ->where('clientRequestPanel.activeRequest.due_at', $context['clientRequest']->due_at->toIso8601String()));
    }

    public function test_client_cannot_submit_message_to_completed_request(): void
    {
        $context = $this->context();
        $issued = $this->issue($context);

        app(ReferralClientRequestService::class)->complete(
            $context['clientRequest']->fresh(),
            $context['agencyUser'],
        );

        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']])
            ->assertRedirect(route('track.request.index'));

        $this->from(route('track.request.index'))
            ->post(route('track.request.messages.store'), ['body' => 'A late reply'])
            ->assertNotFound();

        $this->assertDatabaseCount('referral_client_messages', 0);
    }

    public function test_cancelled_request_denies_client_access(): void
    {
        $context = $this->context();
        $issued = $this->issue($context);

        app(ReferralClientRequestService::class)->cancel(
            $context['clientRequest']->fresh(),
            $context['agencyUser'],
        );

        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']])
            ->assertNotFound();
    }
}
