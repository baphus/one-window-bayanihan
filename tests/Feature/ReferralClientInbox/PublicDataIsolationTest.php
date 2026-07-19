<?php

namespace Tests\Feature\ReferralClientInbox;

class PublicDataIsolationTest extends ReferralClientInboxTestCase
{
    public function test_token_page_uses_tracking_show_with_minimal_request_panel(): void
    {
        $context = $this->context();
        $issued = $this->issue($context);

        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']])
            ->assertRedirect(route('track.request.index'));

        $this->get(route('track.request.index'))->assertInertia(fn ($page) => $page
            ->component('Tracking/Show')
            ->where('clientRequestPanel.state', 'ready')
            ->has('clientRequestPanel.activeRequest', fn ($request) => $request
                ->has('type')
                ->has('title')
                ->has('instructions')
                ->has('due_at')
                ->has('status')
                ->has('agency_name')
                ->has('checklist')
                ->has('messages')
                ->missing('summary')
                ->missing('case')
                ->missing('referral')
                ->missing('otherRequests')
                ->missing('comments'))
            ->where('clientRequestPanel.actions.reply', route('track.request.messages.store'))
            ->where('clientRequestPanel.actions.requestReplacement', route('track.request.replacement')));
    }

    public function test_public_request_has_no_upload_endpoint_or_action(): void
    {
        $this->assertFalse(app('router')->getRoutes()->getByName('track.request.upload') !== null);
        $this->assertFalse(app('router')->getRoutes()->getByName('track.request.attachments.store') !== null);
    }

    public function test_no_session_renders_generic_request_access_page(): void
    {
        $this->get(route('track.request.index'))->assertInertia(fn ($page) => $page
            ->component('Tracking/RequestAccess')
            ->where('exchangeUrl', route('track.request.exchange'))
            ->missing('token')
            ->missing('request')
            ->missing('case')
            ->missing('clientRequestPanel'));
    }
}
