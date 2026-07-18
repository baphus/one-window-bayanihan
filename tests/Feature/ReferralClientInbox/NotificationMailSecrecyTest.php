<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Mail\ClientRequestMail;
use App\Models\CaseNotification;
use App\Notifications\ReferralClientRequestActivity;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class NotificationMailSecrecyTest extends ReferralClientInboxTestCase
{
    public function test_queued_delivery_history_and_activity_payloads_are_token_free(): void
    {
        $context = $this->context();
        Mail::fake();

        $this->actingAs($context['agencyUser'])
            ->post(route('referrals.client-requests.access.reissue', $context['clientRequest']))
            ->assertRedirect();

        $delivery = CaseNotification::query()->where('type', 'client_request_delivery')->latest()->firstOrFail();
        $deliveryData = json_encode($delivery->toArray());
        $this->assertStringNotContainsString('token', $deliveryData);
        $this->assertStringNotContainsString('instructions', $deliveryData);
        $this->assertStringNotContainsString('recipient_snapshot', $deliveryData);
        $this->assertStringNotContainsString('magic', $deliveryData);
        $this->assertSame('queued', $delivery->data['status']);

        $activity = (new ReferralClientRequestActivity(
            'client_reply',
            $context['clientRequest']->id,
            $context['referral']->id,
            $context['clientRequest']->title,
            'CLIENT_RESPONDED',
        ))->toDatabase($context['agencyUser']);
        $activityData = json_encode($activity);
        $this->assertStringNotContainsString('token', $activityData);
        $this->assertStringNotContainsString('instructions', $activityData);
        $this->assertStringNotContainsString('body', $activityData);

        Mail::assertQueued(ClientRequestMail::class, function (ClientRequestMail $mail): bool {
            $content = $mail->content();
            $this->assertInstanceOf(ShouldBeEncrypted::class, $mail);
            $this->assertInstanceOf(ShouldQueue::class, $mail);
            $this->assertSame('emails.client-request', $content->markdown);
            $this->assertArrayNotHasKey('instructions', $content->with);
            $this->assertArrayNotHasKey('body', $content->with);

            return true;
        });
    }
}
