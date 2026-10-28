<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The case details page names the final approver and every lifecycle actor;
 * the cases list exposes the approver only once the case has been approved.
 */
class CaseApprovalDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function makeCase(?User $reviewer = null): CaseFile
    {
        return CaseFile::factory()->open()->create([
            'client_id' => Client::factory()->create()->id,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => $reviewer ? now() : null,
        ]);
    }

    public function test_cases_index_exposes_reviewer_only_after_approval(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $reviewer = User::factory()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'name' => 'Approver Ana',
        ]);

        $approved = $this->makeCase($reviewer);
        $notApproved = $this->makeCase();

        $response = $this->actingAs($admin)->get(route('cases.index'));

        $response->assertOk();
        $rows = collect($response->viewData('page')['props']['cases']['data'])->keyBy('id');

        $this->assertSame('Approver Ana', $rows[$approved->id]['reviewer']['name']);
        $this->assertNull($rows[$notApproved->id]['reviewer']);
    }

    public function test_case_details_exposes_final_approver_and_lifecycle_actors(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'name' => 'Admin Bea',
        ]);
        $reviewer = User::factory()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'name' => 'Approver Ana',
        ]);

        $case = $this->makeCase($reviewer);

        foreach ([
            ['APPROVE', $reviewer, ['status' => 'PENDING_REVIEW'], ['status' => 'OPEN']],
            ['CLOSE', $admin, ['status' => 'OPEN'], ['status' => 'CLOSED']],
        ] as [$action, $user, $old, $new]) {
            AuditLog::create([
                'action' => $action,
                'module' => 'case',
                'entity_id' => $case->id,
                'entity_label' => $case->case_number,
                'old_value' => $old,
                'new_value' => $new,
                'user_id' => $user->id,
                'timestamp' => now(),
                'ip_address' => 'cli',
                'user_agent' => 'test',
                'request_id' => fake()->uuid(),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('cases.show', $case->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Case/Show')
                ->where('approver.name', 'Approver Ana')
                ->where('approver.role', 'Case Manager')
                ->where('lifecycle', function ($lifecycle) {
                    $lifecycle = collect($lifecycle);

                    return $lifecycle->contains(fn ($event) => $event['label'] === 'Approved'
                            && $event['actor'] === 'Approver Ana'
                            && $event['role'] === 'Case Manager')
                        && $lifecycle->contains(fn ($event) => $event['label'] === 'Closed'
                            && $event['actor'] === 'Admin Bea'
                            && $event['role'] === 'Administrator');
                })
            );
    }
}
