<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * NEXT_OF_KIN filing paths: linked-OFW via selected_client_id; inline OFW via
 * new_ofw with filer's own client as the ofw_client_id anchor. OFW must file
 * against their own client regardless of payload.
 */
class NextOfKinFilingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ThrottleRequests::class]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function basicFilingPayload(array $overrides = []): array
    {
        return array_merge([
            'vulnerability_indicator' => 'None',
            'case_issue_id' => null,
            'summary' => null,
            'category_ids' => [],
            'client' => [
                'first_name' => 'Client',
                'last_name' => 'Dummy',
                'date_of_birth' => '1990-01-01',
                'sex' => 'Male',
                'email' => 'dummy@example.test',
                'contact_number' => '+639170000000',
            ],
            'address' => [
                'region' => 'Region VII',
                'province' => 'Cebu',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Lahug',
            ],
            'employment' => [
                'employer_name' => 'Employer',
                'country' => 'UAE',
                'start_date' => '2020-01-01',
                'end_date' => '2021-01-01',
                'is_present' => false,
                'last_country' => 'Qatar',
                'last_position' => 'Worker',
                'date_of_arrival' => '2021-02-01',
            ],
            'consent' => true,
        ], $overrides);
    }

    /**
     * Build a NEXT_OF_KIN filer + an OFW linked to that filer's client.
     */
    private function buildNokWithLinkedOfw(): array
    {
        $filerUser = User::factory()->mfaEnabled()->create([
            'role' => UserRole::OFW->value,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
        $filerClient = Client::factory()->create([
            'type' => 'NEXT_OF_KIN',
            'user_id' => $filerUser->id,
            'first_name' => 'Nok',
            'last_name' => 'Filer',
        ]);
        $filerUser->client_id = $filerClient->id;
        $filerUser->save();

        $linkedOfw = Client::factory()->create([
            'type' => 'OFW',
            'ofw_client_id' => $filerClient->id,
            'first_name' => 'OFW',
            'last_name' => 'Linked',
        ]);

        return ['filer' => $filerUser, 'linkedOfw' => $linkedOfw];
    }

    /**
     * Build an OFW filer whose own client has no linked OFWs yet.
     */
    private function buildOfwSelfFiler(): User
    {
        $user = User::factory()->mfaEnabled()->create([
            'role' => UserRole::OFW->value,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
        $client = Client::factory()->create([
            'type' => 'OFW',
            'user_id' => $user->id,
            'first_name' => 'Self',
            'last_name' => 'OFW',
        ]);
        $user->client_id = $client->id;
        $user->save();

        return $user;
    }

    // ── tests ───────────────────────────────────────────────────────────────

    public function test_nok_cannot_file_for_linked_ofw_even_with_forged_client_selection(): void
    {
        ['filer' => $filer, 'linkedOfw' => $linkedOfw] = $this->buildNokWithLinkedOfw();

        $this->actingAsWithMfa($filer)->postJson(route('cases.store'), $this->basicFilingPayload([
            'selected_client_id' => $linkedOfw->id,
        ]))->assertForbidden();

        $this->assertSame(0, CaseFile::count());
    }

    public function test_nok_cannot_file_inline_new_ofw(): void
    {
        ['filer' => $filer] = $this->buildNokWithLinkedOfw();
        $response = $this->actingAsWithMfa($filer)->postJson(route('cases.store'), $this->basicFilingPayload([
            'new_ofw' => [
                'first_name' => 'New',
                'last_name' => 'OFW',
                'date_of_birth' => '1992-06-15',
                'sex' => 'Female',
                'email' => 'newofw@example.test',
                'contact_number' => '+639180000000',
            ],
        ]));

        $response->assertForbidden();
        $this->assertSame(0, CaseFile::count());
        $this->assertDatabaseMissing('clients', ['email' => 'newofw@example.test']);
    }

    public function test_nok_case_and_intake_get_redirect_to_dashboard_with_office_notice(): void
    {
        ['filer' => $filer] = $this->buildNokWithLinkedOfw();
        $this->actingAsWithMfa($filer);
        $message = 'Requests filed by next of kin must be submitted through DMW staff. Please visit the DMW Region VII office for assistance.';

        $this->get(route('cases.create'))->assertRedirect(route('dashboard'))->assertSessionHas('error', $message);
        $this->get(route('ofw.intake'))->assertRedirect(route('dashboard'))->assertSessionHas('error', $message);
    }

    public function test_nok_cannot_submit_intake(): void
    {
        ['filer' => $filer] = $this->buildNokWithLinkedOfw();

        $this->actingAsWithMfa($filer)->post(route('ofw.intake.store'), [
            'summary' => 'Help needed',
            'consent' => true,
        ])->assertForbidden();

        $this->assertSame(0, CaseFile::count());
    }

    public function test_staff_can_access_filing_pages_for_nok_and_ofw_client_types(): void
    {
        foreach ([UserRole::ADMIN, UserRole::CASE_MANAGER] as $role) {
            foreach (['NEXT_OF_KIN', 'OFW'] as $type) {
                $user = User::factory()->mfaEnabled()->create([
                    'role' => $role->value,
                    'mfa_secret' => 'JBSWY3DPEHPK3PXP',
                ]);
                $client = Client::factory()->create(['type' => $type, 'user_id' => $user->id]);
                $user->client_id = $client->id;
                $user->save();

                $this->actingAsWithMfa($user)->get(route('cases.create'))->assertOk();
            }
        }
    }

    public function test_ofw_ignores_selected_client_id_and_uses_own(): void
    {
        $ofw = $this->buildOfwSelfFiler();
        $otherOfw = Client::factory()->create([
            'type' => 'OFW',
            'first_name' => 'Other',
            'last_name' => 'OFW',
        ]);
        $category = CaseCategory::factory()->create();
        $caseIssue = CaseIssue::create(['name' => 'Unpaid wages', 'is_active' => true]);

        $response = $this->actingAsWithMfa($ofw)->postJson(route('cases.store'), $this->basicFilingPayload([
            'category_ids' => [$category->id],
            'case_issue_id' => $caseIssue->id,
            'selected_client_id' => $otherOfw->id, // stray payload
        ]));

        $response->assertRedirect();

        $case = CaseFile::where('user_id', $ofw->id)
            ->where('status', CaseFile::STATUS_PENDING_REVIEW)
            ->latest()
            ->first();

        $this->assertNotNull($case);
        // Must be the filer's own linked client, NOT the selected one.
        $this->assertEquals($ofw->client_id, $case->client_id);
        $this->assertNotEquals($otherOfw->id, $case->client_id);
        $this->assertEquals(CaseFile::STATUS_PENDING_REVIEW, $case->status);
    }
}
