<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\Client;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * CM-created "new client" drafts must not publish into a clients row that
 * duplicates an existing record carrying the same email. Matching mirrors the
 * self-filing join semantics (LOWER(TRIM(email))); an explicit
 * confirm_duplicate_client flag is the escape hatch.
 */
class ClientDedupPublishTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CaseService $service;

    private CaseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->service = app(CaseService::class);
        $this->category = CaseCategory::factory()->create(['is_active' => true]);
    }

    private function newClientCaseData(string $email = 'helper@example.com'): array
    {
        return [
            'client_type' => 'OFW',
            'category_id' => $this->category->id,
            'client' => [
                'first_name' => 'Helper',
                'last_name' => 'Doe',
                'middle_name' => null,
                'suffix' => null,
                'email' => $email,
                'date_of_birth' => '1990-01-01',
                'sex' => 'Female',
                'contact_number' => '09171234567',
            ],
            'address' => [
                'region' => 'VII',
                'province' => 'Cebu',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Lahug',
                'street' => '',
            ],
            'consent' => true,
        ];
    }

    public function test_publishing_new_client_draft_duplicating_existing_email_is_blocked(): void
    {
        $existing = Client::factory()->create([
            'first_name' => 'Existing',
            'last_name' => 'Client',
            'email' => 'helper@example.com',
        ]);

        $case = $this->service->createCase($this->newClientCaseData(), $this->user->id);

        try {
            $this->service->publishDraft($case->id, $this->user->id);
            $this->fail('Expected ValidationException for duplicate client email.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('duplicate_client', $e->errors());
            $this->assertStringContainsString($existing->email, $e->errors()['duplicate_client'][0]);
        }

        // The blocked publish must not create a client row or open the case.
        $this->assertSame(1, Client::where('email', 'helper@example.com')->count());
        $case->refresh();
        $this->assertNull($case->client_id);
        $this->assertSame('DRAFT', $case->status);
    }

    public function test_confirm_duplicate_client_allows_publish_when_email_matches(): void
    {
        Client::factory()->create(['email' => 'helper@example.com']);

        $case = $this->service->createCase($this->newClientCaseData(), $this->user->id);

        // No exception: the Case Manager explicitly confirmed a new record.
        $this->service->publishDraft($case->id, $this->user->id, true);

        $case->refresh();
        $this->assertNotNull($case->client_id);
        $this->assertSame('OPEN', $case->status);
        // A second row is created only because the CM confirmed it.
        $this->assertSame(2, Client::where('email', 'helper@example.com')->count());
    }

    public function test_publishing_case_linked_to_existing_client_is_not_flagged(): void
    {
        $client = Client::factory()->create([
            'email' => 'linked@example.com',
            'sex' => 'FEMALE',
            'contact_number' => '09171234567',
            'date_of_birth' => '1990-01-01',
        ]);
        $client->addresses()->create([
            'region' => 'VII',
            'province' => 'Cebu',
            'city_municipality' => 'Cebu City',
            'barangay' => 'Lahug',
            'street' => '',
        ]);

        $case = $this->service->createCase([
            'client_type' => 'OFW',
            'selected_client_id' => $client->id,
            'category_id' => $this->category->id,
            'client' => [
                'first_name' => 'Existing',
                'last_name' => 'Client',
                'middle_name' => null,
                'suffix' => null,
                'email' => 'linked@example.com',
                'date_of_birth' => '1990-01-01',
                'sex' => 'Female',
                'contact_number' => '09171234567',
            ],
            'consent' => true,
        ], $this->user->id);

        $this->service->publishDraft($case->id, $this->user->id);

        $case->refresh();
        $this->assertSame($client->id, $case->client_id);
        $this->assertSame('OPEN', $case->status);
        $this->assertSame(1, Client::where('email', 'linked@example.com')->count());
    }

    public function test_email_with_different_case_is_not_a_duplicate(): void
    {
        Client::factory()->create(['email' => 'Other@Example.com']);

        // Case-insensitive + trim-insensitive: same address, different casing.
        $case = $this->service->createCase(
            $this->newClientCaseData('other@example.com'),
            $this->user->id,
        );

        $this->expectException(ValidationException::class);
        $this->service->publishDraft($case->id, $this->user->id);
    }

    public function test_publish_without_client_email_is_not_duplicate_checked(): void
    {
        // A NEXT_OF_KIN case needs no client email, so dedup has nothing to match.
        $data = $this->newClientCaseData();
        $data['client_type'] = 'NEXT_OF_KIN';
        $data['client']['email'] = null;
        $data['selected_nok_index'] = 0;
        $data['next_of_kin'] = [[
            'first_name' => 'Nok',
            'last_name' => 'Kin',
            'relationship' => 'Spouse',
            'phone_number' => '09171234567',
            'email' => 'nok@example.com',
        ]];

        $case = $this->service->createCase($data, $this->user->id);
        $this->service->publishDraft($case->id, $this->user->id);

        $case->refresh();
        $this->assertNotNull($case->client_id);
        $this->assertSame('OPEN', $case->status);
    }
}
