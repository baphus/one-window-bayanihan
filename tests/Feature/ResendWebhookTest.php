<?php

namespace Tests\Feature;

use App\Models\EmailEvent;
use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ResendWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/webhooks/resend';

    private const PROVIDER_ID = '56761188-7520-42d8-8898-ff6fc54ce618';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.resend.webhook_secret', $this->signingSecret());
    }

    // ---------------------------------------------------------------------
    // Signature verification
    // ---------------------------------------------------------------------

    #[Test]
    public function valid_signature_is_accepted(): void
    {
        $this->makeLog();

        $this->postSigned($this->payload('email.delivered'))->assertOk();
    }

    #[Test]
    public function wrong_secret_is_rejected(): void
    {
        $this->makeLog();

        $payload = $this->payload('email.delivered');
        $body = json_encode($payload);
        $id = 'msg_'.Str::random(12);
        $timestamp = (string) now()->getTimestamp();

        $response = $this->postRaw($payload, [
            'svix-id' => $id,
            'svix-timestamp' => $timestamp,
            'svix-signature' => $this->sign($id, $timestamp, $body, 'whsec_'.base64_encode('a-different-key')),
        ]);

        $response->assertStatus(401);
        $this->assertSame(0, EmailEvent::count());
    }

    #[Test]
    public function tampered_body_is_rejected(): void
    {
        $this->makeLog();

        // Sign one payload, then send a different one with that signature.
        $signed = $this->payload('email.delivered');
        $body = json_encode($signed);
        $id = 'msg_'.Str::random(12);
        $timestamp = (string) now()->getTimestamp();
        $signature = $this->sign($id, $timestamp, $body);

        $tampered = $this->payload('email.bounced');

        $this->postRaw($tampered, [
            'svix-id' => $id,
            'svix-timestamp' => $timestamp,
            'svix-signature' => $signature,
        ])->assertStatus(401);

        $this->assertSame('sent', $this->makeLog()->fresh()->status);
    }

    #[Test]
    public function missing_signature_headers_are_rejected(): void
    {
        $this->makeLog();

        $this->postRaw($this->payload('email.delivered'), [])->assertStatus(401);
    }

    #[Test]
    public function stale_timestamp_is_rejected(): void
    {
        $this->makeLog();

        $payload = $this->payload('email.delivered');
        $body = json_encode($payload);
        $id = 'msg_'.Str::random(12);
        // Outside the 5-minute replay tolerance.
        $timestamp = (string) now()->subMinutes(10)->getTimestamp();

        $this->postRaw($payload, [
            'svix-id' => $id,
            'svix-timestamp' => $timestamp,
            'svix-signature' => $this->sign($id, $timestamp, $body),
        ])->assertStatus(401);
    }

    #[Test]
    public function non_numeric_timestamp_is_rejected(): void
    {
        $this->makeLog();

        $payload = $this->payload('email.delivered');
        $id = 'msg_'.Str::random(12);

        $this->postRaw($payload, [
            'svix-id' => $id,
            'svix-timestamp' => 'not-a-timestamp',
            'svix-signature' => $this->sign($id, 'not-a-timestamp', json_encode($payload)),
        ])->assertStatus(401);
    }

    #[Test]
    public function endpoint_fails_closed_when_no_secret_is_configured(): void
    {
        Config::set('services.resend.webhook_secret', null);
        $this->makeLog();

        $this->postSigned($this->payload('email.delivered'))->assertStatus(401);
        $this->assertSame(0, EmailEvent::count());
    }

    #[Test]
    public function a_matching_signature_among_several_is_accepted(): void
    {
        // Svix sends multiple v1 signatures during secret rotation. Checking
        // only the first would break rotation.
        $this->makeLog();

        $payload = $this->payload('email.delivered');
        $body = json_encode($payload);
        $id = 'msg_'.Str::random(12);
        $timestamp = (string) now()->getTimestamp();

        $stale = $this->sign($id, $timestamp, $body, 'whsec_'.base64_encode('previous-key'));
        $current = $this->sign($id, $timestamp, $body);

        $this->postRaw($payload, [
            'svix-id' => $id,
            'svix-timestamp' => $timestamp,
            'svix-signature' => $stale.' '.$current,
        ])->assertOk();

        $this->assertSame('delivered', EmailLog::first()->status);
    }

    // ---------------------------------------------------------------------
    // Idempotency
    // ---------------------------------------------------------------------

    #[Test]
    public function replaying_the_same_svix_id_records_one_event(): void
    {
        $this->makeLog();

        $payload = $this->payload('email.delivered');
        $svixId = 'msg_replayed';

        $this->postSigned($payload, $svixId)->assertOk();
        $this->postSigned($payload, $svixId)->assertOk();

        $this->assertSame(1, EmailEvent::count());
    }

    // ---------------------------------------------------------------------
    // Status mapping
    // ---------------------------------------------------------------------

    #[Test]
    public function delivered_event_sets_status_and_timestamp(): void
    {
        $log = $this->makeLog();

        $this->postSigned($this->payload('email.delivered'))->assertOk();

        $log->refresh();
        $this->assertSame('delivered', $log->status);
        $this->assertNotNull($log->delivered_at);

        $event = EmailEvent::first();
        $this->assertSame('email.delivered', $event->event_type);
        $this->assertSame($log->id, $event->email_log_id);
        $this->assertSame(self::PROVIDER_ID, $event->provider_message_id);
    }

    #[Test]
    public function bounced_event_records_the_reason(): void
    {
        $log = $this->makeLog();

        $payload = $this->payload('email.bounced');
        $payload['data']['bounce'] = [
            'type' => 'Permanent',
            'subType' => 'Suppressed',
            'message' => 'The recipient address is on the suppression list.',
        ];

        $this->postSigned($payload)->assertOk();

        $log->refresh();
        $this->assertSame('bounced', $log->status);
        $this->assertStringContainsString('Permanent', $log->error_message);
        $this->assertStringContainsString('suppression list', $log->error_message);
    }

    #[Test]
    public function complained_event_sets_status(): void
    {
        $log = $this->makeLog();

        $this->postSigned($this->payload('email.complained'))->assertOk();

        $this->assertSame('complained', $log->fresh()->status);
    }

    #[Test]
    public function delivery_delayed_event_sets_delayed_status(): void
    {
        $log = $this->makeLog();

        $this->postSigned($this->payload('email.delivery_delayed'))->assertOk();

        $this->assertSame('delayed', $log->fresh()->status);
    }

    #[Test]
    public function opened_event_is_recorded_without_changing_status(): void
    {
        // Open tracking depends on remote image loading, so it must not be
        // treated as a delivery state.
        $log = $this->makeLog(['status' => 'delivered']);

        $this->postSigned($this->payload('email.opened'))->assertOk();

        $this->assertSame('delivered', $log->fresh()->status);
        $this->assertSame(1, EmailEvent::where('event_type', 'email.opened')->count());
    }

    // ---------------------------------------------------------------------
    // Out-of-order delivery
    // ---------------------------------------------------------------------

    #[Test]
    public function a_late_sent_event_does_not_overwrite_a_bounce(): void
    {
        $log = $this->makeLog(['status' => 'bounced']);

        $this->postSigned($this->payload('email.sent'))->assertOk();

        $this->assertSame('bounced', $log->fresh()->status);
    }

    #[Test]
    public function a_late_delivered_event_does_not_overwrite_a_bounce(): void
    {
        $log = $this->makeLog(['status' => 'bounced']);

        $this->postSigned($this->payload('email.delivered'))->assertOk();

        $log->refresh();
        $this->assertSame('bounced', $log->status);
        // The delivery timestamp is still captured even though the status holds.
        $this->assertNotNull($log->delivered_at);
    }

    #[Test]
    public function a_bounce_after_delivery_advances_the_status(): void
    {
        $log = $this->makeLog(['status' => 'delivered']);

        $this->postSigned($this->payload('email.bounced'))->assertOk();

        $this->assertSame('bounced', $log->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // Payload handling
    // ---------------------------------------------------------------------

    #[Test]
    public function unknown_event_types_are_acknowledged_but_not_stored(): void
    {
        // A 4xx here would make the provider retry forever and risk the
        // endpoint being disabled.
        $this->makeLog();

        $this->postSigned($this->payload('contact.created'))->assertOk();

        $this->assertSame(0, EmailEvent::count());
    }

    #[Test]
    public function payload_without_an_email_id_is_rejected(): void
    {
        $payload = $this->payload('email.delivered');
        unset($payload['data']['email_id']);

        $this->postSigned($payload)->assertStatus(422);
    }

    #[Test]
    public function payload_without_a_type_is_rejected(): void
    {
        $payload = $this->payload('email.delivered');
        unset($payload['type']);

        $this->postSigned($payload)->assertStatus(422);
    }

    #[Test]
    public function events_for_unknown_messages_are_still_recorded(): void
    {
        // No matching email_logs row: dropping these would hide exactly the
        // cases worth investigating.
        $this->postSigned($this->payload('email.bounced'))->assertOk();

        $event = EmailEvent::first();
        $this->assertNotNull($event);
        $this->assertNull($event->email_log_id);
        $this->assertSame(self::PROVIDER_ID, $event->provider_message_id);
    }

    #[Test]
    public function provider_timestamp_is_stored(): void
    {
        $this->makeLog();

        $payload = $this->payload('email.delivered');
        $payload['created_at'] = '2026-07-27T08:15:30.000Z';

        $this->postSigned($payload)->assertOk();

        $this->assertSame(
            '2026-07-27 08:15:30',
            EmailEvent::first()->occurred_at->utc()->format('Y-m-d H:i:s')
        );
    }

    #[Test]
    public function unparseable_provider_timestamp_does_not_reject_the_event(): void
    {
        $this->makeLog();

        $payload = $this->payload('email.delivered');
        $payload['created_at'] = 'definitely not a date';

        $this->postSigned($payload)->assertOk();

        $this->assertSame(1, EmailEvent::count());
        $this->assertNull(EmailEvent::first()->occurred_at);
    }

    // ---------------------------------------------------------------------
    // Correlation from the send side
    // ---------------------------------------------------------------------

    #[Test]
    public function message_sent_stores_the_provider_message_id(): void
    {
        Event::dispatch(new MessageSent($this->sentMessageWithResendId(self::PROVIDER_ID)));

        $log = EmailLog::where('to_email', 'recipient@example.com')->first();

        $this->assertNotNull($log);
        $this->assertSame(self::PROVIDER_ID, $log->provider_message_id);
    }

    #[Test]
    public function message_sent_without_a_provider_header_still_logs(): void
    {
        // The log, smtp and array transports never set the header.
        Event::dispatch(new MessageSent($this->sentMessageWithResendId(null)));

        $log = EmailLog::where('to_email', 'recipient@example.com')->first();

        $this->assertNotNull($log);
        $this->assertNull($log->provider_message_id);
    }

    #[Test]
    public function duplicate_sends_with_the_same_provider_id_log_once(): void
    {
        Event::dispatch(new MessageSent($this->sentMessageWithResendId(self::PROVIDER_ID)));
        Event::dispatch(new MessageSent($this->sentMessageWithResendId(self::PROVIDER_ID)));

        $this->assertSame(1, EmailLog::where('provider_message_id', self::PROVIDER_ID)->count());
    }

    #[Test]
    public function a_send_then_its_delivery_webhook_reconcile(): void
    {
        // End-to-end: the header captured on send is the key the webhook uses.
        Event::dispatch(new MessageSent($this->sentMessageWithResendId(self::PROVIDER_ID)));

        $this->postSigned($this->payload('email.delivered'))->assertOk();

        $log = EmailLog::where('provider_message_id', self::PROVIDER_ID)->first();
        $this->assertSame('delivered', $log->status);
        $this->assertSame(1, $log->events()->count());
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function makeLog(array $attributes = []): EmailLog
    {
        return EmailLog::firstOrCreate(
            ['provider_message_id' => self::PROVIDER_ID],
            array_merge([
                'to_email' => 'recipient@example.com',
                'subject' => 'Sending this example',
                'mailable_type' => 'App\Mail\OtpMail',
                'status' => 'sent',
                'sent_at' => now(),
            ], $attributes)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $type): array
    {
        return [
            'type' => $type,
            'created_at' => '2026-07-27T08:00:00.000Z',
            'data' => [
                'email_id' => self::PROVIDER_ID,
                'from' => 'Bayanihan <onboarding@resend.dev>',
                'to' => ['recipient@example.com'],
                'subject' => 'Sending this example',
            ],
        ];
    }

    private function postSigned(array $payload, ?string $svixId = null): TestResponse
    {
        $body = json_encode($payload);
        $id = $svixId ?? 'msg_'.Str::random(12);
        $timestamp = (string) now()->getTimestamp();

        return $this->postRaw($payload, [
            'svix-id' => $id,
            'svix-timestamp' => $timestamp,
            'svix-signature' => $this->sign($id, $timestamp, $body),
        ]);
    }

    /**
     * json() encodes the body with json_encode($data), which is exactly what
     * the signature is computed over.
     */
    private function postRaw(array $payload, array $headers): TestResponse
    {
        return $this->json('POST', self::ENDPOINT, $payload, $headers);
    }

    private function sign(string $id, string $timestamp, string $body, ?string $secret = null): string
    {
        $secret ??= $this->signingSecret();
        $key = base64_decode(substr($secret, strlen('whsec_')));

        return 'v1,'.base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$body, $key, true));
    }

    private function signingSecret(): string
    {
        return 'whsec_'.base64_encode('bayanihan-test-signing-key');
    }

    /**
     * Build a SentMessage the way ResendTransport leaves it: the provider id is
     * added as a header after the API call succeeds.
     */
    private function sentMessageWithResendId(?string $providerId): SentMessage
    {
        $email = new Email;
        $email->from(new Address('noreply@example.com'));
        $email->to(new Address('recipient@example.com'));
        $email->subject('Sending this example');
        $email->text('Body');

        if ($providerId !== null) {
            $email->getHeaders()->addHeader('X-Resend-Email-ID', $providerId);
        }

        return new SentMessage(new SymfonySentMessage($email, Envelope::create($email)));
    }
}
