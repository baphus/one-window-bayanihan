<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientRequest;
use App\Models\User;
use App\Services\ReferralClientRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ReferralClientRequestRaceSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id, 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['user_id' => $manager->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);
        $request = ReferralClientRequest::factory()->create(['referral_id' => $referral->id]);
        $link = ReferralClientAccessLink::factory()->create(['request_id' => $request->id, 'issued_by' => $agencyUser->id]);

        return compact('agencyUser', 'case', 'referral', 'request', 'link');
    }

    public function test_client_message_after_revoke_is_rejected(): void
    {
        $context = $this->context();
        $service = app(ReferralClientRequestService::class);
        $service->revokeAccessLink($context['link'], $context['agencyUser']);

        $this->expectException(LogicException::class);
        $service->sendClientMessage($context['request'], 'A reply', $context['link']);
    }

    public function test_terminal_transition_revokes_links_and_rejects_client_message(): void
    {
        $context = $this->context();
        $service = app(ReferralClientRequestService::class);
        $service->complete($context['request'], $context['agencyUser']);

        $this->assertNotNull($context['link']->fresh()->revoked_at);
        $this->expectException(LogicException::class);
        $service->sendClientMessage($context['request'], 'A late reply', $context['link']);
    }

    public function test_reopen_does_not_reactivate_old_link(): void
    {
        $context = $this->context();
        $service = app(ReferralClientRequestService::class);
        $service->complete($context['request'], $context['agencyUser']);
        $service->reopen($context['request']->fresh(), $context['agencyUser']);

        $this->assertSame(ReferralClientRequest::STATUS_OPEN, $context['request']->fresh()->status);
        $this->assertNotNull($context['link']->fresh()->revoked_at);
        $this->expectException(LogicException::class);
        $service->sendClientMessage($context['request']->fresh(), 'An old-link reply', $context['link']->fresh());
    }
}
