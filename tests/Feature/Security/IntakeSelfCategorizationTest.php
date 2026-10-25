<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Intake boundary hardening: an OFW filing their own case must not be able to
 * attach staff-review categories or a case issue. Categories are decided by a
 * case manager at review-approval time, so smuggled ids must never survive.
 */
class IntakeSelfCategorizationTest extends TestCase
{
    use RefreshDatabase;

    private function ofwWithClient(): User
    {
        $client = Client::factory()->create();

        return User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);
    }

    public function test_intake_ignores_caller_supplied_categories(): void
    {
        $category = CaseCategory::factory()->create(['is_active' => true]);
        $ofw = $this->ofwWithClient();

        $response = $this->actingAs($ofw)->post(route('ofw.intake.store'), [
            'summary' => 'Please help me.',
            'vulnerability_indicator' => 'None',
            'consent' => true,
            'category_id' => $category->id,
            'category_ids' => [$category->id],
        ]);

        $response->assertRedirect(route('ofw.dashboard'));

        $case = CaseFile::latest('id')->firstOrFail();

        $this->assertNull($case->category_id);
        $this->assertFalse($case->categories()->exists());
    }

    public function test_intake_ignores_caller_supplied_case_issue(): void
    {
        $ofw = $this->ofwWithClient();

        $response = $this->actingAs($ofw)->post(route('ofw.intake.store'), [
            'summary' => 'Please help me.',
            'vulnerability_indicator' => 'None',
            'consent' => true,
            'case_issue_id' => fake()->uuid(),
        ]);

        $response->assertRedirect(route('ofw.dashboard'));

        $case = CaseFile::latest('id')->firstOrFail();

        $this->assertNull($case->case_issue_id);
    }
}
