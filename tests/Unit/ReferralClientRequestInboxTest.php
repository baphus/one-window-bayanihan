<?php

namespace Tests\Unit;

use App\Http\Controllers\ReferralClientRequestController;
use App\Mail\ClientRequestMail;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralClientRequest;
use App\Notifications\ReferralClientRequestActivity;
use App\Services\ReferralClientAccessService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralClientRequestInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_request_mail_is_encrypted_and_queued(): void
    {
        $mail = new ClientRequestMail(
            new ReferralClientRequest,
            str_repeat('a', 43),
            'https://example.test/track/request/opaque-token',
        );

        $this->assertInstanceOf(ShouldBeEncrypted::class, $mail);
        $this->assertInstanceOf(ShouldQueue::class, $mail);
        $this->assertSame('https://example.test/track/request/opaque-token', $mail->magicLink);
    }

    public function test_notification_payload_contains_only_request_safe_fields(): void
    {
        $payload = (new ReferralClientRequestActivity(
            'client_reply',
            'request-id',
            'referral-id',
            'Documents needed',
            'CLIENT_RESPONDED',
        ))->toDatabase(new \stdClass);

        $this->assertSame([
            'type' => 'referral_client_request_client_reply',
            'request_id' => 'request-id',
            'referral_id' => 'referral-id',
            'title' => 'Documents needed',
            'status' => 'CLIENT_RESPONDED',
            'url' => '/referrals/referral-id/client-requests',
        ], $payload);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertArrayNotHasKey('body', $payload);
    }

    public function test_short_or_empty_tokens_are_non_mutating_and_unusable(): void
    {
        $this->assertNull(app(ReferralClientAccessService::class)->resolveUsableToken('too-short'));
    }

    public function test_tracking_panel_contract_is_token_free(): void
    {
        $panel = [
            'state' => 'ready',
            'activeRequest' => [
                'type' => 'QUESTION',
                'title' => 'A request',
                'instructions' => 'Please reply.',
                'due_at' => null,
                'status' => 'OPEN',
                'agency_name' => 'Agency',
                'checklist' => [],
                'messages' => [],
            ],
            'actions' => [
                'reply' => route('track.request.messages.store'),
                'requestReplacement' => route('track.request.replacement'),
            ],
        ];

        $this->assertSame('ready', $panel['state']);
        $this->assertSame(['type', 'title', 'instructions', 'due_at', 'status', 'agency_name', 'checklist', 'messages'], array_keys($panel['activeRequest']));
        $this->assertSame(['reply', 'requestReplacement'], array_keys($panel['actions']));
        $this->assertStringNotContainsString('token', json_encode($panel));
    }

    public function test_delivery_history_contains_status_but_no_token_or_magic_url(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => Agency::factory()->create()->id,
        ]);
        $clientRequest = ReferralClientRequest::factory()->create(['referral_id' => $referral->id]);
        $controller = app(ReferralClientRequestController::class);
        $method = new \ReflectionMethod($controller, 'recordDelivery');
        $method->invoke($controller, $clientRequest, null, 'not_sent_no_email');

        $notification = $case->user->notifications()->latest()->firstOrFail();
        $this->assertSame('referral_client_request_delivery_not_sent_no_email', $notification->data['type']);
        $this->assertSame($clientRequest->id, $notification->data['request_id']);
        $this->assertSame($referral->id, $notification->data['referral_id']);
        $this->assertStringNotContainsString('token', json_encode($notification->data));
        $this->assertStringNotContainsString('http', json_encode($notification->data));
    }
}
