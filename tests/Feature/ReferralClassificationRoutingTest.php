<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReferralClassificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggested_agency_accepts_independent_concerns_and_blocks_trimmed_repeat(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $agency = Agency::factory()->create(['is_active' => true]);
        $category = CaseCategory::factory()->create();
        $category->suggestedAgencies()->attach($agency);
        $data = ['case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => $category->id, 'concern' => 'Wage claim'];
        $service = app(ReferralService::class);
        $first = $service->createReferral($data, $manager->id);
        $second = $service->createReferral([...$data, 'concern' => 'Contract advice'], $manager->id);
        $this->assertSame('Wage claim', $first->fresh()->concern);
        $this->assertSame('Contract advice', $second->fresh()->concern);
        $this->assertNull($first->override_reason);
        $this->assertTrue($first->category->is($category));

        try {
            $service->createReferral([...$data, 'concern' => '  Wage claim  '], $manager->id);
            $this->fail('Repeated concern must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('concern', $exception->errors());
        }
        $this->assertSame(2, Referral::where('case_id', $case->id)->count());
    }

    public function test_http_requires_classification_and_override_without_bypassing_active_destination(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $agency = Agency::factory()->create(['is_active' => true]);
        $category = CaseCategory::factory()->create();
        $data = ['case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => $category->id, 'concern' => 'Medical assistance'];
        $this->actingAsWithMfa($manager);
        $this->post(route('referrals.store'), ['case_id' => $case->id, 'agcy_id' => $agency->id])
            ->assertSessionHasErrors(['category_id', 'concern']);
        $this->post(route('referrals.store'), $data)->assertSessionHasErrors('override_reason');
        $this->post(route('referrals.store'), [...$data, 'override_reason' => 'Specialist assistance'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Specialist assistance', Referral::sole()->override_reason);
        $agency->update(['is_active' => false]);
        $this->post(route('referrals.store'), [...$data, 'override_reason' => 'Still requested', 'concern' => 'Another concern'])
            ->assertSessionHasErrors('agcy_id');
        $agency->update(['is_active' => true]);
        $category->update(['is_active' => false]);
        $this->post(route('referrals.store'), [...$data, 'override_reason' => 'Still requested'])
            ->assertSessionHasErrors('category_id');
        $this->assertSame(1, Referral::count());
    }

    public function test_category_filter_uses_referral_classification_not_case_classification(): void
    {
        $category = CaseCategory::factory()->create();
        $other = CaseCategory::factory()->create();
        $case = CaseFile::factory()->withIntake()->create();
        $matching = Referral::factory()->create(['case_id' => $case->id, 'category_id' => $category->id]);
        Referral::factory()->create(['case_id' => $case->id, 'category_id' => $other->id]);

        $results = app(ReferralService::class)->getReferrals(['category_id' => $category->id]);

        $this->assertSame([$matching->id], $results->pluck('id')->all());
    }

    public function test_service_rejects_blank_concern_and_deleted_references_without_request_validation(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $agency = Agency::factory()->create(['is_active' => true]);
        $category = CaseCategory::factory()->create();
        $data = ['case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => $category->id, 'concern' => 'Valid concern', 'override_reason' => 'Specialist agency'];
        $service = app(ReferralService::class);
        foreach (['concern', 'category_id', 'agcy_id'] as $field) {
            $invalid = $data;
            if ($field === 'concern') {
                $invalid['concern'] = '   ';
            } elseif ($field === 'category_id') {
                $category->delete();
            } else {
                $category->restore();
                $agency->delete();
            }
            try {
                $service->createReferral($invalid, $manager->id);
                $this->fail('Invalid classification must fail.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
        $this->assertSame(0, Referral::count());
    }

    public function test_create_and_authorized_show_expose_classification_but_other_agency_cannot_read_it(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create(['is_active' => true]);
        $category = CaseCategory::factory()->create();
        $category->suggestedAgencies()->attach($agency);
        $this->actingAsWithMfa($manager)->get(route('referrals.create'))->assertInertia(fn ($page) => $page
            ->has('categories', 1)->where('categories.0.suggested_agency_ids.0', $agency->id));
        $case = CaseFile::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => $category->id, 'concern' => 'Private legal concern']);
        $this->get(route('referrals.show', $referral))->assertInertia(fn ($page) => $page
            ->where('referral.concern', 'Private legal concern')->where('referral.category.id', $category->id)
            ->where('caseIdentity.first_name', 'Maria')->where('caseIdentity.last_name', 'Santos')
            ->missing('referral.case_file.client')->missing('caseIdentity.password'));
        $peer = Referral::factory()->create(['case_id' => $referral->case_id, 'concern' => 'Other agency private concern', 'override_reason' => 'Other agency private reason']);
        $recipient = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $this->actingAsWithMfa($recipient)->get(route('referrals.show', $referral))->assertInertia(fn ($page) => $page
            ->missing('relatedReferrals.0.concern')->missing('relatedReferrals.0.override_reason'));
        $other = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => Agency::factory()]);
        $this->actingAsWithMfa($other)->get(route('referrals.show', $referral))->assertForbidden();
    }
}
