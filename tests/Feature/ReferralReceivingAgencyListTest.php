<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agency;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralReceivingAgencyListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    public function test_rejected_referrals_are_visible_to_the_receiving_agency_and_status_filter_resets_page(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);

        Referral::factory()->count(16)->rejected()->create(['agcy_id' => $agency->id]);

        $pageTwo = $this->actingAs($user)->withHeader('X-Inertia', 'true')
            ->get(route('referrals.index', ['page' => 2]));
        $pageTwo->assertOk();
        $this->assertSame(2, $pageTwo->json('props.referrals.current_page'));

        $filtered = $this->actingAs($user)->withHeader('X-Inertia', 'true')
            ->get(route('referrals.index', ['status' => 'REJECTED']));
        $filtered->assertOk();
        $this->assertSame(1, $filtered->json('props.referrals.current_page'));
        $this->assertSame(16, $filtered->json('props.referrals.total'));
        $this->assertSame('REJECTED', $filtered->json('props.filters.status'));
    }

    public function test_receiving_agency_list_isolated_from_other_agencies(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);

        $visible = Referral::factory()->rejected()->create([
            'agcy_id' => $agency->id,
            'required_services' => 'Visible rejected service',
        ]);
        Referral::factory()->rejected()->create([
            'agcy_id' => $otherAgency->id,
            'required_services' => 'Other agency rejected service',
        ]);

        $response = $this->actingAs($user)->withHeader('X-Inertia', 'true')
            ->get(route('referrals.index', ['status' => 'REJECTED']));

        $response->assertOk();
        $ids = collect($response->json('props.referrals.data'))->pluck('id');
        $this->assertTrue($ids->contains($visible->id));
        $this->assertCount(1, $ids);
        $this->assertSame(1, $response->json('props.stats.rejected'));
    }

    public function test_referral_list_paginates_with_validated_page_size_and_orders_the_complete_scoped_result_set(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);

        Referral::factory()->count(11)->create(['agcy_id' => $agency->id, 'status' => 'PENDING']);
        Referral::factory()->count(11)->rejected()->create(['agcy_id' => $agency->id]);
        Referral::factory()->rejected()->create(['agcy_id' => Agency::factory()->create()->id]);

        $pageTwo = $this->actingAs($user)->withHeader('X-Inertia', 'true')
            ->get(route('referrals.index', [
                'sort' => 'status',
                'direction' => 'asc',
                'per_page' => 10,
                'page' => 2,
            ]));

        $pageTwo->assertOk();
        $this->assertSame(3, $pageTwo->json('props.referrals.last_page'));
        $this->assertSame(10, $pageTwo->json('props.referrals.per_page'));
        $this->assertSame(22, $pageTwo->json('props.referrals.total'));
        $this->assertSame('status', $pageTwo->json('props.filters.sort'));
        $this->assertSame('asc', $pageTwo->json('props.filters.direction'));
        $this->assertSame(2, $pageTwo->json('props.referrals.current_page'));
        $this->assertSame('PENDING', $pageTwo->json('props.referrals.data.0.status'));
        $this->assertSame('REJECTED', $pageTwo->json('props.referrals.data.1.status'));

        $largerPage = $this->actingAs($user)->withHeader('X-Inertia', 'true')
            ->get(route('referrals.index', ['per_page' => 25]));

        $largerPage->assertOk();
        $this->assertSame(22, $largerPage->json('props.referrals.total'));
        $this->assertSame(25, $largerPage->json('props.referrals.per_page'));
        $this->assertCount(22, $largerPage->json('props.referrals.data'));
    }
}
