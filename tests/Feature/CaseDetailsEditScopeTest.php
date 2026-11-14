<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseDetailsEditScopeTest extends TestCase
{
    use RefreshDatabase;

    protected User $cm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
    }

    public function test_update_route_rejects_case_wide_classification_and_narrative_mutation(): void
    {
        $case = $this->makeCase(['summary' => 'Original statement', 'first_name' => 'Original']);
        $this->actingAsWithMfa($this->cm)->patch(route('cases.update', $case), [
            'category_id' => 'forged', 'category_ids' => ['forged'], 'case_issue_id' => 'forged',
            'first_name' => 'Altered', 'summary' => 'Altered summary',
            'correction_reason' => 'Unauthorized classification attempt',
        ])->assertForbidden();
        $this->assertSame('Original', $case->fresh()->first_name);
        $this->assertSame('Original statement', $case->fresh()->summary);
    }

    public function test_factual_correction_requires_assignment_and_reason(): void
    {
        $case = $this->makeCase(['assigned_case_manager_id' => $this->cm->id]);
        $this->actingAsWithMfa($this->cm)->patch(route('cases.update', $case), ['first_name' => 'Changed'])
            ->assertSessionHasErrors('correction_reason');
        $this->assertSame('Original', $case->fresh()->first_name);
    }

    private function makeCase(array $attributes = []): CaseFile
    {
        return CaseFile::factory()->withIntake()->create(array_merge([
            'user_id' => $this->cm->id,
            'status' => 'OPEN',
            'first_name' => 'Original',
            'summary' => 'Original narrative',
        ], $attributes));
    }
}
