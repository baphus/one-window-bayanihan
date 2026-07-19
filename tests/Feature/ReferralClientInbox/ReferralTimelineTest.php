<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientRequest;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_includes_safe_client_request_and_response_events(): void
    {
        $referral = Referral::factory()->create([
            'agcy_id' => ($agency = Agency::factory()->create())->id,
            'case_id' => ($case = CaseFile::factory()->create())->id,
        ]);
        $request = ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'type' => ReferralClientRequest::TYPE_DOCUMENT_REQUEST,
            'instructions' => 'Sensitive instructions must not enter the timeline.',
        ]);
        $request->forceFill([
            'status' => ReferralClientRequest::STATUS_COMPLETED,
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ])->saveQuietly();
        $accessLink = ReferralClientAccessLink::factory()->create(['request_id' => $request->id]);
        ReferralClientMessage::factory()->create([
            'request_id' => $request->id,
            'body' => 'Private client response body.',
            'sender_kind' => ReferralClientMessage::SENDER_CLIENT_ACCESS,
            'user_id' => null,
            'access_link_id' => $accessLink->id,
            'created_at' => now()->subMinutes(30),
        ]);

        $timeline = app(ReferralService::class)->getReferralTimeline($referral->fresh());
        $requestEvents = collect($timeline)->whereIn('type', ['client_request', 'client_response', 'client_request_status']);

        $this->assertCount(3, $requestEvents);
        $this->assertSame('Client document request created', $requestEvents->firstWhere('type', 'client_request')['title']);
        $this->assertSame('Client', $requestEvents->firstWhere('type', 'client_response')['actor']);
        $this->assertSame($agency->name, $requestEvents->firstWhere('type', 'client_request_status')['actor']);
        $this->assertStringNotContainsString('Sensitive instructions', json_encode($requestEvents->all()));
        $this->assertStringNotContainsString('Private client response body', json_encode($requestEvents->all()));
        $this->assertSame(['id', 'type', 'title', 'description', 'timestamp', 'actor'], array_keys($requestEvents->first()));
    }
}
