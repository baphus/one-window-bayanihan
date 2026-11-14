<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CaseIndexAndPurgeLowsTest extends TestCase
{
    use RefreshDatabase;

    private function trashedCase(User $manager): CaseFile
    {
        $case = CaseFile::factory()->create(['user_id' => $manager->id, 'status' => 'OPEN']);
        $case->delete();
        $this->assertDatabaseHas('cases', ['id' => $case->id, 'is_deleted' => true]);

        return $case;
    }

    public function test_case_create_sends_no_profile_or_classification_props(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);
        $props = $this->actingAsWithMfa($manager)->get(route('cases.create'))->assertOk()->viewData('page')['props'];
        foreach (['client', 'categories', 'caseIssues', 'profile', 'linkedOfws'] as $key) {
            $this->assertArrayNotHasKey($key, $props);
        }
        $this->assertArrayHasKey('documentSlots', $props);
    }

    public function test_ofw_intake_sends_no_profile_or_classification_props(): void
    {
        $ofw = User::factory()->create([
            'role' => UserRole::OFW->value, 'first_name' => 'Maria', 'last_name' => 'Cruz',
            'date_of_birth' => '1990-01-01', 'sex' => 'FEMALE',
        ]);
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);
        $props = $this->actingAsWithMfa($ofw)->get(route('ofw.intake'))->assertOk()->viewData('page')['props'];
        foreach (['client', 'profile', 'categories', 'caseIssues'] as $key) {
            $this->assertArrayNotHasKey($key, $props);
        }
        $this->assertArrayHasKey('documentSlots', $props);
    }

    public function test_retired_client_and_case_issue_routes_are_absent(): void
    {
        foreach (['clients.export-excel', 'clients.export-count', 'clients.avatar.store', 'clients.avatar.destroy', 'api.clients.index', 'api.clients.email-check', 'api.clients.show', 'api.my-linked-ofws', 'case-issues.quick', 'admin.case-issues.index', 'admin.case-issues.store', 'admin.case-issues.update', 'admin.case-issues.destroy', 'admin.case-issues.reactivate'] as $route) {
            $this->assertFalse(Route::has($route), $route.' remains registered.');
        }
    }

    public function test_purge_command_rejects_unsafe_retention_and_preserves_case(): void
    {
        foreach (['-3', '0', 'abc'] as $days) {
            $case = $this->trashedCase(User::factory()->create(['role' => UserRole::CASE_MANAGER->value]));
            $this->assertSame(1, \Artisan::call('cases:purge-trashed', ['--days' => $days]));
            $this->assertDatabaseHas('cases', ['id' => $case->id, 'is_deleted' => true]);
        }
    }

    public function test_purge_service_guard_and_valid_retention(): void
    {
        $case = $this->trashedCase(User::factory()->create(['role' => UserRole::CASE_MANAGER->value]));
        $service = app(CaseService::class);
        $this->assertSame(0, $service->purgeTrashedCases(-1));
        $this->assertSame(0, $service->purgeTrashedCases(0));
        DB::table('cases')->where('id', $case->id)->update(['deleted_at' => now()->subDays(400)]);
        $this->assertSame(0, \Artisan::call('cases:purge-trashed', ['--days' => '200']));
        $this->assertDatabaseMissing('cases', ['id' => $case->id]);
    }
}
