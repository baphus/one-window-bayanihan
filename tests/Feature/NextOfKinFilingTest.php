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
        $filerUser = User::factory()->create([
            'role' => UserRole::OFW->value,
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
        $user = User::factory()->create(['role' => UserRole::OFW->value]);
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

    public function test_nok_files_for_linked_ofw(): void
    {
        ['filer' => $filer, 'linkedOfw' => $linkedOfw] = $this->buildNokWithLinkedOfw();
        $category = CaseCategory::factory()->create();
        $caseIssue = CaseIssue::create(['name' => 'Unpaid wages', 'is_active' => true]);

        $response = $this->actingAs($filer)->postJson(route('cases.store'), $this->basicFilingPayload([
            'category_ids' => [$category->id],
            'case_issue_id' => $caseIssue->id,
            'selected_client_id' => $linkedOfw->id,
        ]));

        if ($response->status() === 422) {
            $this->fail('cases.store 422: '.json_encode($response->json('errors')));
        }
        $response->assertRedirect();

        // NOK filer: case must belong to the selected linked OFW.
        $case = CaseFile::where('client_id', $linkedOfw->id)
            ->where('status', CaseFile::STATUS_PENDING_REVIEW)
            ->latest()
            ->first();

        $this->assertNotNull($case);
        $this->assertEquals($linkedOfw->id, $case->client_id);
        $this->assertEquals(CaseFile::STATUS_PENDING_REVIEW, $case->status);
    }

    public function test_nok_inline_new_ofw_links_back_to_filer(): void
    {
        ['filer' => $filer] = $this->buildNokWithLinkedOfw();
        $category = CaseCategory::factory()->create();
        $caseIssue = CaseIssue::create(['name' => 'Unpaid wages', 'is_active' => true]);

        $response = $this->actingAs($filer)->postJson(route('cases.store'), $this->basicFilingPayload([
            'category_ids' => [$category->id],
            'case_issue_id' => $caseIssue->id,
            'new_ofw' => [
                'first_name' => 'New',
                'last_name' => 'OFW',
                'date_of_birth' => '1992-06-15',
                'sex' => 'Female',
                'email' => 'newofw@example.test',
                'contact_number' => '+639180000000',
            ],
        ]));

        $response->assertRedirect();

        // Inline OFW: case must belong to a NEW OFW client linked back to the filer.
        $case = CaseFile::whereHas('client', fn ($q) => $q
            ->where('type', 'OFW')
            ->where('ofw_client_id', $filer->client_id))
            ->where('status', CaseFile::STATUS_PENDING_REVIEW)
            ->latest()
            ->first();

        $this->assertNotNull($case);
        $this->assertEquals(CaseFile::STATUS_PENDING_REVIEW, $case->status);

        // Inline OFW must be linked back to the filer's client via ofw_client_id.
        $newOfw = Client::find($case->client_id);
        $this->assertNotNull($newOfw);
        $this->assertEquals('OFW', $newOfw->type);
        $this->assertEquals($filer->client_id, $newOfw->ofw_client_id);
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

        $response = $this->actingAs($ofw)->postJson(route('cases.store'), $this->basicFilingPayload([
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
