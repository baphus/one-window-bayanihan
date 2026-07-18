<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Services\ReferralClientAccessService;
use App\Services\TrackingService;

class CapabilitySecurityTest extends ReferralClientInboxTestCase
{
    public function test_valid_exchange_redirects_without_token_and_stores_only_capability_ids(): void
    {
        $context = $this->context();
        $issued = $this->issue($context);

        $response = $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']]);

        $response->assertRedirect(route('track.request.index'));
        $this->assertStringNotContainsString($issued['raw_token'], $response->headers->get('Location'));
        $session = $this->app['session']->get('client_request_access');
        $this->assertSame($context['clientRequest']->id, $session['request_id']);
        $this->assertSame($issued['link']->id, $session['link_id']);
        $this->assertArrayNotHasKey('token', $session);
    }

    public function test_revoked_expired_and_reissued_tokens_are_denied(): void
    {
        $context = $this->context();
        $access = app(ReferralClientAccessService::class);
        $first = $this->issue($context);
        $access->revoke($first['link'], $context['agencyUser']);
        $this->post(route('track.request.exchange'), ['token' => $first['raw_token']])->assertNotFound();

        $expired = $access->issue($context['clientRequest'], $context['agencyUser'], [
            'email' => $context['client']->email,
            'name' => 'Test Client',
        ]);
        $expired['link']->update(['expires_at' => now()->subMinute()]);
        $this->post(route('track.request.exchange'), ['token' => $expired['raw_token']])->assertNotFound();

        $current = $this->issue($context);
        $replacement = $access->reissue($context['clientRequest'], $context['agencyUser'], [
            'email' => $context['client']->email,
            'name' => 'Test Client',
        ]);
        $this->post(route('track.request.exchange'), ['token' => $current['raw_token']])->assertNotFound();
        $this->post(route('track.request.exchange'), ['token' => $replacement['raw_token']])->assertRedirect(route('track.request.index'));
    }

    public function test_client_reply_requires_request_capability_not_tracking_session(): void
    {
        $context = $this->context();
        $this->withSession([
            TrackingService::SESSION_KEY => [
                'tracker_number' => $context['case']->tracker_number,
                'email' => $context['client']->email,
            ],
        ])->post(route('track.request.messages.store'), ['body' => 'Attempted message'])
            ->assertNotFound();
    }

    public function test_session_request_and_link_ids_must_match(): void
    {
        $first = $this->context();
        $second = $this->context();
        $issued = $this->issue($first);

        $this->withSession([
            'client_request_access' => [
                'request_id' => $second['clientRequest']->id,
                'link_id' => $issued['link']->id,
                'expires_at' => $issued['expires_at']->toIso8601String(),
            ],
        ])->get(route('track.request.index'))->assertInertia(fn ($page) => $page
            ->component('Tracking/RequestAccess')
            ->missing('clientRequestPanel')
            ->missing('request')
            ->missing('case'));
    }
}
