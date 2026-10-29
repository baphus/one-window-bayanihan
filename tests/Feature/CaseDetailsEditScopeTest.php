<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The case detail screen's "Edit Case Details" modal exposes only two
 * editable fields: issue/concern and category. Status, vulnerability and
 * narrative are not accepted by the update endpoint, so a payload carrying
 * them is ignored rather than partially applied.
 */
class CaseDetailsEditScopeTest extends TestCase
{
    use RefreshDatabase;

    protected User $cm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cm = User::factory()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'password' => bcrypt('password'),
        ]);
    }

    public function test_issue_and_category_are_persisted_by_the_update_route(): void
    {
        $issue = CaseIssue::create(['name' => 'Wage Theft', 'is_active' => true]);
        $category = CaseCategory::create(['name' => 'Legal Assistance', 'is_active' => true]);
        $case = $this->makeCase();

        $response = $this->actingAs($this->cm)
            ->patch("/cases/{$case->id}", [
                'case_issue_id' => $issue->id,
                'category_ids' => [$category->id],
            ]);

        $response->assertStatus(302);

        $fresh = $case->fresh();
        $this->assertSame($issue->id, $fresh->case_issue_id);
        $this->assertEqualsCanonicalizing([$category->id], $fresh->categories()->pluck('case_categories.id')->all());
    }

    public function test_status_summary_and_vulnerability_in_the_payload_are_ignored(): void
    {
        $issue = CaseIssue::create(['name' => 'Unpaid Wages', 'is_active' => true]);
        $category = CaseCategory::create(['name' => 'Livelihood Assistance', 'is_active' => true]);
        $case = $this->makeCase([
            'status' => 'OPEN',
            'summary' => 'Original narrative.',
            'vulnerability_indicator' => 'Solo Parent',
            'nok_vulnerability_indicator' => 'Senior Citizen',
        ]);

        $response = $this->actingAs($this->cm)
            ->patch("/cases/{$case->id}", [
                'status' => 'CLOSED',
                'summary' => 'Rewritten narrative.',
                'vulnerability_indicator' => 'PWD',
                'nok_vulnerability_indicator' => 'None',
                'case_issue_id' => $issue->id,
                'category_ids' => [$category->id],
            ]);

        $response->assertStatus(302);

        $fresh = $case->fresh();
        $this->assertSame('OPEN', $fresh->status, 'Status must not change through the details edit route.');
        $this->assertSame('Original narrative.', $fresh->summary);
        $this->assertSame('Solo Parent', $fresh->vulnerability_indicator);
        $this->assertSame('Senior Citizen', $fresh->nok_vulnerability_indicator);
        $this->assertSame($issue->id, $fresh->case_issue_id, 'The permitted fields are still applied.');
    }

    public function test_clearing_the_issue_is_allowed(): void
    {
        $issue = CaseIssue::create(['name' => 'Temporary Issue', 'is_active' => true]);
        $category = CaseCategory::create(['name' => 'Housing Support', 'is_active' => true]);
        $case = $this->makeCase(['case_issue_id' => $issue->id]);

        $response = $this->actingAs($this->cm)
            ->patch("/cases/{$case->id}", [
                'case_issue_id' => null,
                'category_ids' => [$category->id],
            ]);

        $response->assertStatus(302);
        $this->assertNull($case->fresh()->case_issue_id);
    }

    public function test_inactive_category_is_rejected(): void
    {
        $inactive = CaseCategory::create(['name' => 'Retired Category', 'is_active' => false]);
        $case = $this->makeCase();

        $response = $this->actingAs($this->cm)
            ->patch("/cases/{$case->id}", ['category_ids' => [$inactive->id]]);

        $response->assertSessionHasErrors('category_ids.0');
        $this->assertEmpty($case->fresh()->categories()->pluck('case_categories.id')->all());
    }

    public function test_inline_quick_added_issue_can_be_assigned_to_the_case(): void
    {
        $category = CaseCategory::create(['name' => 'Health Services', 'is_active' => true]);
        $case = $this->makeCase();

        // The modal creates the issue through this endpoint, then submits the
        // returned id as case_issue_id.
        $created = $this->actingAs($this->cm)
            ->postJson(route('case-issues.quick'), ['name' => 'Lost Passport']);

        $created->assertStatus(200);
        $issueId = $created->json('id');
        $this->assertNotEmpty($issueId);

        $response = $this->actingAs($this->cm)
            ->patch("/cases/{$case->id}", [
                'case_issue_id' => $issueId,
                'category_ids' => [$category->id],
            ]);

        $response->assertStatus(302);
        $this->assertSame($issueId, $case->fresh()->case_issue_id);
    }

    private function makeCase(array $attributes = []): CaseFile
    {
        return CaseFile::factory()->create(array_merge([
            'user_id' => $this->cm->id,
            'status' => 'OPEN',
            'summary' => 'Original narrative.',
        ], $attributes));
    }
}
