<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Models\CaseNotification;

class ReplacementRequestIsIdempotentTest extends ReferralClientInboxTestCase
{
    public function test_a_second_replacement_click_does_not_notify_the_agency_again(): void
    {
        $context = $this->context();
        $this->establishSession($context);

        $this->post(route('track.request.replacement'))->assertRedirect(route('track.request.index'));

        $notificationsAfterFirst = $this->replacementNotifications($context);
        $this->assertSame(1, $notificationsAfterFirst, 'the first click must actually notify');

        // The route is public and session-bound; without the guard the OFW can
        // refresh it and refill the agency's notification list each time. The
        // per-IP throttle does not stop a phone switching networks.
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('track.request.replacement'))->assertRedirect(route('track.request.index'));
        }

        $this->assertSame($notificationsAfterFirst, $this->replacementNotifications($context));
    }

    public function test_the_ofw_still_sees_a_success_message_when_the_request_is_a_repeat(): void
    {
        $context = $this->context();
        $this->establishSession($context);

        $this->post(route('track.request.replacement'))->assertRedirect(route('track.request.index'));

        $this->post(route('track.request.replacement'))
            ->assertRedirect(route('track.request.index'))
            ->assertSessionHas('success');
    }

    private function establishSession(array $context): void
    {
        $issued = $this->issue($context);
        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']])
            ->assertRedirect(route('track.request.index'));
    }

    private function replacementNotifications(array $context): int
    {
        return CaseNotification::query()
            ->where('type', 'client_request_replacement_requested')
            ->where('data->request_id', $context['clientRequest']->id)
            ->count();
    }
}
