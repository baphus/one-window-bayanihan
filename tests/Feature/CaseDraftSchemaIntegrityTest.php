<?php

namespace Tests\Feature;

use App\Models\CaseDraft;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Client;
use App\Models\User;
use App\Services\CaseDraftIdentifierGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class CaseDraftSchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_draft_provenance_rollback_removes_selected_nok_evidence(): void
    {
        $migration = require database_path('migrations/2026_07_17_000003_add_case_draft_provenance_and_rls.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('case_drafts', 'selected_nok_evidence'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('case_drafts', 'selected_nok_evidence'));
    }

    public function test_discarded_draft_with_selected_nok_requires_provenance_evidence(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create();
        $nokId = (string) Str::uuid();
        DB::table('next_of_kin')->insert([
            'id' => $nokId,
            'client_id' => $client->id,
            'first_name' => 'Nok',
            'last_name' => 'Person',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23514');

        CaseDraft::create($this->draftAttributes($owner->id, [
            'state' => CaseDraft::STATE_DISCARDED,
            'selected_nok_id' => $nokId,
            'selected_nok_evidence' => null,
        ]));
    }

    public function test_consent_acceptance_without_notice_version_is_rejected(): void
    {
        $owner = User::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23514');

        CaseDraft::create($this->draftAttributes($owner->id, [
            'consent_accepted_at' => now(),
            'consent_notice_version' => null,
        ]));
    }

    public function test_case_draft_rls_policy_is_owner_scoped_for_reads_and_writes(): void
    {
        $policy = DB::table('pg_policies')
            ->where('tablename', 'case_drafts')
            ->where('policyname', 'case_drafts_owner_all')
            ->first();
        $rls = DB::selectOne("SELECT relrowsecurity FROM pg_class WHERE oid = 'case_drafts'::regclass");

        $this->assertTrue((bool) $rls->relrowsecurity);
        $this->assertNotNull($policy);
        $this->assertStringContainsString('app.current_user_id', $policy->qual);
        $this->assertStringContainsString('app.current_user_id', $policy->with_check);
    }

    public function test_identifier_generator_exhaustion_uses_retryable_unique_violation_contract(): void
    {
        $this->makeCaseFile('CASE-EXHAUSTED');
        $method = new ReflectionMethod(CaseDraftIdentifierGenerator::class, 'generateUnique');
        $method->setAccessible(true);

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23505');

        $method->invoke(
            new CaseDraftIdentifierGenerator,
            static fn (): string => 'CASE-EXHAUSTED',
            'case_number',
        );
    }

    public function test_notification_event_key_is_unique_and_failed_claims_can_be_reclaimed(): void
    {
        $case = $this->makeCaseFile('CASE-NOTIFICATION');
        $attributes = [
            'case_id' => $case->id,
            'client_email' => 'client@example.test',
            'type' => 'case_updated',
            'title' => 'Case updated',
            'message' => 'Case updated.',
            'event_key' => 'case:'.$case->id.':updated',
        ];

        $claim = CaseNotification::claimDelivery($attributes);
        $this->assertNotNull($claim);
        $this->assertSame('processing', $claim->delivery_status);
        $this->assertNull(CaseNotification::claimDelivery($attributes));

        $claim->markDeliveryFailed();
        $reclaimed = CaseNotification::claimDelivery($attributes);

        $this->assertNotNull($reclaimed);
        $this->assertSame($claim->id, $reclaimed->id);
        $this->assertSame('processing', $reclaimed->delivery_status);
    }

    /** @return array<string, mixed> */
    private function draftAttributes(string $ownerId, array $overrides = []): array
    {
        return array_merge([
            'owner_id' => $ownerId,
            'payload_schema_version' => 1,
            'revision' => 1,
            'state' => CaseDraft::STATE_EDITING,
        ], $overrides);
    }

    private function makeCaseFile(string $caseNumber): CaseFile
    {
        $user = User::factory()->create();

        return CaseFile::create([
            'case_number' => $caseNumber,
            'tracker_number' => 'OWBAP-'.Str::upper(Str::random(7)),
            'client_type' => 'OFW',
            'status' => 'OPEN',
            'user_id' => $user->id,
        ]);
    }
}
