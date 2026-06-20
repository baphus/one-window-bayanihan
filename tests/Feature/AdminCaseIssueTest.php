<?php

namespace Tests\Feature;

use App\Models\CaseIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCaseIssueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'ADMIN']);
    }

    public function test_admin_can_list_issues(): void
    {
        CaseIssue::create(['name' => 'Fraud', 'sort_order' => 1, 'is_active' => true]);
        CaseIssue::create(['name' => 'Overpayment', 'sort_order' => 2, 'is_active' => true]);

        $response = $this->actingAs($this->admin)->get(route('admin.case-issues.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/CaseIssue/Index')
            ->has('issues', 2)
        );
    }

    public function test_admin_can_create_issue(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.case-issues.store'), [
            'name' => 'Contract Violation',
            'sort_order' => 3,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('case_issues', [
            'name' => 'Contract Violation',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_issue(): void
    {
        $issue = CaseIssue::create(['name' => 'Old Issue', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->patch(route('admin.case-issues.update', $issue->id), [
            'name' => 'Updated Issue',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('case_issues', [
            'id' => $issue->id,
            'name' => 'Updated Issue',
        ]);
    }

    public function test_admin_can_deactivate_issue(): void
    {
        $issue = CaseIssue::create(['name' => 'Temp Issue', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->delete(route('admin.case-issues.destroy', $issue->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('case_issues', [
            'id' => $issue->id,
            'is_deleted' => true,
            'is_active' => false,
        ]);
    }

    public function test_non_admin_cannot_create_issue(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->post(route('admin.case-issues.store'), [
            'name' => 'Hacked',
        ]);

        $response->assertForbidden();
    }

    public function test_validation_requires_name(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.case-issues.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    }
}
