<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_age_uses_latest_eligible_case_and_app_date_on_both_views(): void
    {
        $this->travelTo(Carbon::parse('2025-02-27 16:30:00', 'UTC'));
        config()->set('app.timezone', 'Asia/Manila');
        $this->actingAsWithMfa(User::factory()->create(['role' => 'ADMIN']));
        $owner = User::factory()->create(['role' => 'OFW']);
        CaseFile::factory()->withIntake()->create(['ofw_user_id' => $owner->id, 'date_of_birth' => '1990-01-01', 'intake_date' => '2025-02-28', 'created_at' => now()->subDay()]);
        $latest = CaseFile::factory()->withIntake()->create(['ofw_user_id' => $owner->id, 'date_of_birth' => '2000-03-01', 'intake_date' => '2025-03-01', 'created_at' => now()]);

        $this->get(route('clients.index', ['search' => $latest->case_number]))
            ->assertInertia(fn (Assert $page) => $page->where('clients.data.0.current_age', 24)->etc());
        $this->get(route('clients.show', ['account', $owner->id]))
            ->assertInertia(fn (Assert $page) => $page->where('client.source_case.id', $latest->id)->where('client.profile.current_age', 24)->etc());

        $this->travelTo(Carbon::parse('2025-02-28 16:30:00', 'UTC'));
        $this->get(route('clients.index', ['search' => $latest->case_number]))
            ->assertInertia(fn (Assert $page) => $page->where('clients.data.0.current_age', 25)->etc());
        $this->get(route('clients.show', ['account', $owner->id]))
            ->assertInertia(fn (Assert $page) => $page->where('client.profile.current_age', 25)->etc());
    }

    public function test_current_age_handles_leap_birthday_missing_and_future_birth_dates(): void
    {
        $this->travelTo(Carbon::parse('2024-02-28 12:00:00', 'UTC'));
        $this->actingAsWithMfa(User::factory()->create(['role' => 'ADMIN']));
        $leap = CaseFile::factory()->withIntake()->create(['ofw_user_id' => null, 'date_of_birth' => '2000-02-29', 'intake_date' => '2024-02-28']);
        $missing = CaseFile::factory()->withIntake()->create(['ofw_user_id' => null, 'date_of_birth' => null]);
        $future = CaseFile::factory()->withIntake()->create(['ofw_user_id' => null, 'date_of_birth' => '2025-01-01', 'intake_date' => '2025-01-01']);

        foreach ([[$leap, 23], [$missing, null], [$future, null]] as [$case, $age]) {
            $this->get(route('clients.index', ['search' => $case->case_number]))
                ->assertInertia(fn (Assert $page) => $page->where('clients.data.0.current_age', $age)->etc());
            $this->get(route('clients.show', ['case', $case->id]))
                ->assertInertia(fn (Assert $page) => $page->where('client.profile.current_age', $age)->etc());
        }

        $this->travelTo(Carbon::parse('2024-02-29 12:00:00', 'UTC'));
        $this->get(route('clients.show', ['case', $leap->id]))
            ->assertInertia(fn (Assert $page) => $page->where('client.profile.current_age', 24)->etc());
    }

    public function test_only_staff_can_access_both_directory_routes(): void
    {
        $case = CaseFile::factory()->withIntake()->create(['ofw_user_id' => null]);
        $this->get(route('clients.index'))->assertRedirect();
        $this->get(route('clients.show', ['case', $case->id]))->assertRedirect();

        foreach (['AGENCY', 'OFW'] as $role) {
            $this->actingAsWithMfa(User::factory()->create(['role' => $role]));
            $this->get(route('clients.index'))->assertForbidden();
            $this->get(route('clients.show', ['case', $case->id]))->assertForbidden();
        }
        foreach (['ADMIN', 'CASE_MANAGER'] as $role) {
            $this->actingAsWithMfa(User::factory()->create(['role' => $role]));
            $this->get(route('clients.index'))->assertOk();
            $this->get(route('clients.show', ['case', $case->id]))->assertOk();
        }
    }

    public function test_groups_linked_cases_separates_unlinked_cases_and_counts_global_kpis(): void
    {
        $this->actingAsWithMfa(User::factory()->create(['role' => 'ADMIN']));
        $owner = User::factory()->create(['role' => 'OFW', 'email' => 'owner@example.test']);
        $old = CaseFile::factory()->withIntake()->create(['ofw_user_id' => $owner->id, 'vulnerability_indicator' => 'PWD', 'first_name' => 'Older', 'created_at' => now()->subDay()]);
        $new = CaseFile::factory()->withIntake()->closed()->create(['ofw_user_id' => $owner->id, 'client_type' => 'NEXT_OF_KIN', 'vulnerability_indicator' => 'None', 'first_name' => 'Newer', 'email' => 'newer-case@example.test', 'contact_number' => '09171234567', 'address_city_municipality' => 'Cebu City', 'address_province' => 'Cebu', 'employment_country' => 'Japan', 'employment_status' => 'ON_SITE']);
        $standalone = CaseFile::factory()->withIntake()->create(['ofw_user_id' => null, 'client_type' => 'NEXT_OF_KIN', 'nok_vulnerability_indicator' => 'PWD', 'vulnerability_indicator' => 'None']);
        $another = CaseFile::factory()->withIntake()->create(['ofw_user_id' => null, 'vulnerability_indicator' => 'None']);
        CaseFile::factory()->withIntake()->pendingReview()->create(['ofw_user_id' => $owner->id]);
        CaseFile::factory()->withIntake()->archived()->create(['ofw_user_id' => null]);
        $deleted = CaseFile::factory()->withIntake()->create(['ofw_user_id' => null]);
        $deleted->delete();

        $this->get(route('clients.index', ['search' => $old->case_number, 'status' => 'Both']))
            ->assertInertia(fn (Assert $page) => $page->component('Clients/Index')
                ->where('clients.total', 1)->where('clients.data.0.id', $owner->id)
                ->where('clients.data.0.name', 'Newer Dela Cruz')->where('clients.data.0.case_count', 2)
                ->where('clients.data.0.open_case_count', 1)->where('clients.data.0.closed_case_count', 1)
                ->where('clients.data.0.vulnerability_indicators', ['PWD'])
                ->where('clients.data.0.email', 'newer-case@example.test')->where('clients.data.0.contact_number', '09171234567')
                ->where('clients.data.0.address_city_municipality', 'Cebu City')->where('clients.data.0.address_province', 'Cebu')
                ->where('clients.data.0.employment_country', 'Japan')->where('clients.data.0.employment_status', 'ON_SITE')
                ->missing('kpis.eligible_clients')->where('kpis.ofw_clients', 2)
                ->where('kpis.next_of_kin_clients', 2)->where('kpis.vulnerable_clients', 1)
                ->where('kpis.returning_clients', 1)->missing('kpis.open')->missing('kpis.closed')->missing('kpis.vulnerable')->etc());
        $this->get(route('clients.index', ['status' => 'Closed']))->assertInertia(fn (Assert $page) => $page
            ->where('clients.total', 1)->missing('kpis.eligible_clients')->where('kpis.ofw_clients', 2)
            ->where('kpis.next_of_kin_clients', 2)->where('kpis.vulnerable_clients', 1)->where('kpis.returning_clients', 1)->etc());
        $this->get(route('clients.index', ['search' => $standalone->case_number]))->assertInertia(fn (Assert $page) => $page
            ->where('clients.total', 1)->where('clients.data.0.vulnerability_indicators', [])->etc());
        $this->get(route('clients.index', ['search' => 'owner@example.test']))->assertInertia(fn (Assert $page) => $page->where('clients.total', 1)->etc());
        $this->get(route('clients.index', ['search' => 'Juan']))->assertInertia(fn (Assert $page) => $page->where('clients.total', 2)->etc());
        $this->get(route('clients.show', ['case', $standalone->id]))->assertInertia(fn (Assert $page) => $page->where('cases.total', 1)->where('client.account.state', 'No account linked')->where('client.account.avatar_url', null)->etc());
        $this->get(route('clients.show', ['case', $another->id]))->assertInertia(fn (Assert $page) => $page->where('cases.total', 1)->etc());
        $this->get('/clients/garbage/'.$standalone->id)->assertNotFound();
        $this->get(route('clients.show', ['case', $new->id]))->assertNotFound();
        $this->get(route('clients.show', ['case', $deleted->id]))->assertNotFound();
    }

    public function test_kpis_count_distinct_rows_and_only_linked_repeat_clients(): void
    {
        $this->actingAsWithMfa(User::factory()->create(['role' => 'ADMIN']));
        $owner = User::factory()->create(['role' => 'OFW']);
        CaseFile::factory()->withIntake()->create(['ofw_user_id' => $owner->id, 'client_type' => 'OFW', 'vulnerability_indicator' => 'PWD']);
        CaseFile::factory()->withIntake()->create(['ofw_user_id' => $owner->id, 'client_type' => 'NEXT_OF_KIN', 'vulnerability_indicator' => 'PWD, Solo Parent']);
        CaseFile::factory()->withIntake()->create(['ofw_user_id' => null, 'client_type' => 'OFW', 'vulnerability_indicator' => 'None']);
        CaseFile::factory()->withIntake()->create(['ofw_user_id' => null, 'client_type' => 'OFW', 'vulnerability_indicator' => 'None']);

        $this->get(route('clients.index', ['status' => 'Closed']))->assertInertia(fn (Assert $page) => $page
            ->where('clients.total', 0)->missing('kpis.eligible_clients')->where('kpis.ofw_clients', 3)
            ->where('kpis.next_of_kin_clients', 1)->where('kpis.vulnerable_clients', 1)
            ->where('kpis.returning_clients', 1)->etc());
        $this->get(route('clients.index', ['search' => $owner->email]))->assertInertia(fn (Assert $page) => $page
            ->where('clients.total', 1)->where('clients.data.0.vulnerability_indicators', ['PWD', 'Solo Parent'])->etc());
    }

    public function test_index_paginates_with_stable_id_tie_break(): void
    {
        $this->actingAsWithMfa(User::factory()->create(['role' => 'ADMIN']));
        $cases = CaseFile::factory()->withIntake()->count(16)->create(['ofw_user_id' => null, 'created_at' => now()->subDay()]);
        $ids = $cases->pluck('id')->sort()->values();

        $this->get(route('clients.index'))->assertInertia(fn (Assert $page) => $page
            ->where('clients.total', 16)->has('clients.data', 15)
            ->where('clients.data.0.id', $ids[0])->etc());
        $this->get(route('clients.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('clients.total', 16)->has('clients.data', 1)
            ->where('clients.data.0.id', $ids[15])->etc());
    }

    public function test_detail_uses_latest_created_case_allowlist_and_paginated_owner_history(): void
    {
        $this->actingAsWithMfa(User::factory()->create(['role' => 'CASE_MANAGER']));
        $owner = User::factory()->create(['role' => 'OFW', 'email' => 'account@example.test', 'avatar_url' => 'https://example.test/ofw-avatar.jpg']);
        for ($i = 0; $i < 16; $i++) {
            CaseFile::factory()->withIntake()->create(['ofw_user_id' => $owner->id, 'first_name' => 'Older', 'created_at' => now()->subDay()]);
        }
        $approver = User::factory()->create(['role' => 'CASE_MANAGER', 'name' => 'Approving Manager']);
        $latest = CaseFile::factory()->withIntake()->create(['ofw_user_id' => $owner->id, 'first_name' => 'Case Profile', 'email' => 'case@example.test', 'created_at' => now(), 'reviewed_by' => $approver->id]);
        $this->get(route('clients.show', ['account', $owner->id]))->assertInertia(fn (Assert $page) => $page
            ->where('cases.data.0.approver', 'Approving Manager')->where('cases.data.1.approver', null)->etc());
        $this->get(route('clients.show', ['account', $owner->id]).'?page=2')
            ->assertInertia(fn (Assert $page) => $page->component('Clients/Show')
                ->where('client.profile.first_name', 'Case Profile')->where('client.profile.email', 'case@example.test')
                ->missing('client.profile.proposed_assessments')->missing('client.profile.nok_email')
                ->missing('client.profile.vulnerability_indicator')->missing('client.profile.summary')
                ->where('client.source_case.id', $latest->id)->where('client.account.email', 'account@example.test')
                ->where('client.account.avatar_url', 'https://example.test/ofw-avatar.jpg')
                ->where('cases.total', 17)->where('cases.current_page', 2)->where('cases.data.0.show_url', fn ($url) => str_contains($url, '/cases/'))->etc());

        $owner->update(['is_active' => false]);
        $this->get(route('clients.show', ['account', $owner->id]))->assertInertia(fn (Assert $page) => $page->where('client.account.state', 'Inactive')->etc());
        $owner->delete();
        $this->get(route('clients.show', ['account', $owner->id]))->assertInertia(fn (Assert $page) => $page->where('client.account.state', 'Unavailable')->where('client.account.email', null)->where('client.account.avatar_url', null)->etc());
        $this->get(route('clients.index', ['search' => 'account@example.test']))->assertInertia(fn (Assert $page) => $page->where('clients.total', 0)->where('kpis.ofw_clients', 1)->where('kpis.returning_clients', 1)->etc());
    }
}
