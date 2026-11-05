<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Client;
use App\Models\Referral;
use App\Models\ReferralClientRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfwNotificationsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_bell_route_deep_links_each_notification_type(): void
    {
        $client = Client::factory()->create(['email' => 'client@example.com']);
        $user = User::factory()->create(['role' => UserRole::OFW->value, 'client_id' => $client->id]);
        $case = CaseFile::factory()->open()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $request = ReferralClientRequest::factory()->create(['referral_id' => $referral->id]);

        foreach (
            [
                ['type' => 'client_request_delivery', 'related_url' => null, 'data' => ['request_id' => $request->id, 'referral_id' => $referral->id, 'status' => 'queued']],
                ['type' => 'referral_created', 'related_url' => route('track.show', $case->tracker_number), 'data' => ['referral_id' => $referral->id]],
                ['type' => 'case_updated', 'related_url' => null, 'data' => []],
            ] as $row
        ) {
            CaseNotification::create([
                'case_id' => $case->id,
                'client_email' => $client->email,
                'title' => 'title',
                'message' => 'message',
                ...$row,
            ]);
        }

        $response = $this->actingAsWithMfa($user)->getJson('/my-cases/notifications');

        $response->assertOk();
        $items = collect($response->json('data'))->keyBy('type');

        $this->assertSame(
            route('ofw.case.requests.show', ['case' => $case->id, 'referral' => $referral->id, 'request' => $request->id]),
            $items['client_request_delivery']['action_url'],
        );
        // A public /track link is not a signed-in OFW destination — fall back
        // to the case page rather than the OTP flow.
        $this->assertSame(route('ofw.case.show', $case->id), $items['referral_created']['action_url']);
        $this->assertSame(route('ofw.case.show', $case->id), $items['case_updated']['action_url']);
        $this->assertSame(3, $response->json('meta.unread'));
    }

    public function test_marking_read_is_scoped_to_the_calling_client(): void
    {
        $client = Client::factory()->create(['email' => 'client@example.com']);
        $user = User::factory()->create(['role' => UserRole::OFW->value, 'client_id' => $client->id]);
        $case = CaseFile::factory()->open()->create(['client_id' => $client->id]);
        $notification = CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => $client->email,
            'type' => 'case_updated',
            'title' => 'title',
            'message' => 'message',
        ]);

        $this->actingAsWithMfa($user)
            ->patchJson('/my-cases/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJson(['success' => true]);

        // Already read: not found again (no idempotent second success), and an
        // unknown id is also 404.
        $this->actingAsWithMfa($user)
            ->patchJson('/my-cases/notifications/00000000-0000-0000-0000-000000000000/read')
            ->assertNotFound();
    }
}
