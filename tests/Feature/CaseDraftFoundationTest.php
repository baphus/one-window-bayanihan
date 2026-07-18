<?php

namespace Tests\Feature;

use App\DTOs\CaseDraftPayload;
use App\Encryption\VersionedPayloadEncryptor;
use App\Exceptions\CaseDraftPayloadDecryptionException;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseDraft;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Services\CaseDraftService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CaseDraftFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_draft_schema_casts_and_state_constraints_are_available(): void
    {
        $draft = CaseDraft::create([
            'owner_id' => User::factory()->create()->id,
            'payload_encrypted' => ['schema_version' => 1, 'client_source' => 'NEW'],
            'payload_schema_version' => 1,
            'revision' => 1,
            'state' => CaseDraft::STATE_EDITING,
        ]);

        $this->assertIsArray($draft->refresh()->payload_encrypted);
        $this->assertIsInt($draft->revision);
        $this->assertTrue($draft->isEditing());
        $this->assertFalse($draft->isTerminal());
    }

    public function test_disabled_feature_returns_503_from_the_api_lane(): void
    {
        config(['features.case_drafts.enabled' => false]);
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $this->actingAs($user)
            ->postJson(route('case-drafts.store'), ['client_source' => 'NEW'])
            ->assertStatus(503)
            ->assertJsonPath('code', 'CASE_DRAFTS_DISABLED');
    }

    public function test_disabled_feature_returns_503_before_validating_a_malformed_request(): void
    {
        config(['features.case_drafts.enabled' => false]);
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $this->actingAs($user)
            ->postJson(route('case-drafts.store'), ['client_source' => 'INVALID', 'summary' => ['malicious' => true]])
            ->assertStatus(503)
            ->assertJsonPath('code', 'CASE_DRAFTS_DISABLED');
    }

    public function test_combined_malformed_uuid_inputs_return_controlled_422(): void
    {
        config(['features.case_drafts.enabled' => true]);
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $this->actingAs($user)->postJson(route('case-drafts.store'), [
            'client_source' => 'EXISTING',
            'source_client_id' => 'not-a-uuid',
            'selected_nok_id' => 'also-not-a-uuid',
            'case_issue_id' => 'still-not-a-uuid',
        ])->assertStatus(422)->assertJsonValidationErrors([
            'source_client_id', 'selected_nok_id', 'case_issue_id',
        ]);
    }

    public function test_publish_payload_limits_match_database_backed_field_bounds(): void
    {
        $mutations = [
            'client name' => fn (array &$payload) => $payload['client']['first_name'] = str_repeat('x', 256),
            'address street' => fn (array &$payload) => $payload['address']['street'] = str_repeat('x', 1001),
            'employment name' => fn (array &$payload) => $payload['employment']['employer_name'] = str_repeat('x', 256),
        ];

        foreach ($mutations as $label => $mutate) {
            $payload = $this->newPayload();
            $mutate($payload);

            try {
                CaseDraftPayload::fromArray($payload)->validateForPublish();
                $this->fail("The {$label} database limit was not enforced.");
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors(), "The {$label} failure was not actionable.");
            }
        }
    }

    public function test_future_consent_and_unapproved_notice_version_are_rejected_before_publish(): void
    {
        $future = $this->newPayload();
        $future['consent']['accepted_at'] = now()->addDay()->toIso8601String();
        try {
            CaseDraftPayload::fromArray($future)->validateForPublish();
            $this->fail('Future consent was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('consent.accepted_at', $exception->errors());
        }

        $unapproved = $this->newPayload();
        $unapproved['consent']['notice_version'] = 'v999';
        $this->expectException(ValidationException::class);
        CaseDraftPayload::fromArray($unapproved)->validateForPublish();
    }

    public function test_invalid_canonical_existing_client_email_is_rejected(): void
    {
        $owner = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $client = Client::factory()->create(['sex' => 'MALE', 'email' => 'not-an-email']);
        $client->addresses()->create([
            'region' => 'Region VII', 'province' => 'Cebu',
            'city_municipality' => 'Cebu City', 'barangay' => 'Barangay 1',
        ]);
        $draft = app(CaseDraftService::class)->create([
            'schema_version' => 1, 'client_source' => 'EXISTING',
            'source_client_id' => $client->id, 'client_type' => 'OFW',
            'category_ids' => [$category->id],
        ], $owner->id);

        $this->expectException(ValidationException::class);
        app(CaseDraftService::class)->publish($draft, $owner->id, 1);
    }

    public function test_incomplete_selected_new_nok_returns_422_without_materializing_children(): void
    {
        config(['features.case_drafts.enabled' => true]);
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $category = CaseCategory::factory()->create();
        $temporaryNokId = fake()->uuid();
        $payload = array_merge($this->newPayload(), [
            'client_type' => 'NEXT_OF_KIN',
            'category_ids' => [$category->id],
            'selected_nok_id' => $temporaryNokId,
            'next_of_kin' => [[
                'id' => $temporaryNokId,
                'first_name' => 'Incomplete',
                'last_name' => 'NOK',
                'relationship' => 'Sibling',
                'phone_number' => '09170000000',
            ]],
        ]);
        $draft = app(CaseDraftService::class)->create($payload, $user->id);
        $clientsBefore = Client::count();

        $this->actingAs($user)->postJson(route('case-drafts.publish', $draft), ['expected_revision' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['next_of_kin.email']);
        $this->assertSame($clientsBefore, Client::count());
        $this->assertSame(CaseDraft::STATE_EDITING, $draft->refresh()->state);
    }

    public function test_autosave_allows_a_sustained_two_second_cadence(): void
    {
        config(['features.case_drafts.enabled' => true]);
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $draft = app(CaseDraftService::class)->create($this->newPayload(), $owner->id);

        for ($revision = 1; $revision <= 5; $revision++) {
            $this->actingAs($owner)->putJson(route('case-drafts.update', $draft), [
                'expected_revision' => $revision,
                ...$this->newPayload(),
            ])->assertOk()->assertJsonPath('revision', $revision + 1);
        }
    }

    public function test_new_temporary_selected_nok_can_be_created_saved_and_published_over_http(): void
    {
        Queue::fake();
        config(['features.case_drafts.enabled' => true]);
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $category = CaseCategory::factory()->create();
        $temporaryNokId = fake()->uuid();
        $payload = array_merge($this->newPayload(), [
            'category_ids' => [$category->id],
            'selected_nok_id' => $temporaryNokId,
            'next_of_kin' => [[
                'id' => $temporaryNokId,
                'first_name' => 'Temporary',
                'last_name' => 'NOK',
                'relationship' => 'Sibling',
                'email' => 'temporary.nok@example.test',
            ]],
        ]);

        $this->actingAs($owner)->postJson(route('case-drafts.store'), $payload)
            ->assertCreated()->assertJsonPath('revision', 1);
        $draft = CaseDraft::where('owner_id', $owner->id)->firstOrFail();

        $this->actingAs($owner)->putJson(route('case-drafts.update', $draft), [
            'expected_revision' => 1,
            ...$payload,
        ])->assertOk()->assertJsonPath('revision', 2);

        $this->actingAs($owner)->postJson(route('case-drafts.publish', $draft), ['expected_revision' => 2])
            ->assertOk()->assertJsonPath('state', CaseDraft::STATE_PUBLISHED);
        $this->assertDatabaseHas('cases', ['id' => $draft->refresh()->published_case_id, 'status' => 'OPEN']);
    }

    public function test_strict_payload_rejections_cover_scalar_types_and_existing_profile_fields(): void
    {
        $invalidPayloads = [
            'sex' => fn (array &$payload) => $payload['client']['sex'] = 'UNKNOWN',
            'email' => fn (array &$payload) => $payload['client']['email'] = 'not-an-email',
            'bool' => fn (array &$payload) => $payload['next_of_kin'] = [['id' => fake()->uuid(), 'is_primary' => 'true']],
            'int' => fn (array &$payload) => $payload['next_of_kin'] = [['id' => fake()->uuid(), 'sort_order' => '1']],
            'date' => fn (array &$payload) => $payload['client']['date_of_birth'] = '01/01/1990',
            'notice' => fn (array &$payload) => $payload['consent']['notice_version'] = 'v 1',
        ];

        foreach ($invalidPayloads as $name => $mutate) {
            $payload = $this->newPayload();
            $mutate($payload);
            try {
                CaseDraftPayload::fromArray($payload);
                $this->fail("Invalid {$name} payload was accepted.");
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors(), "{$name} rejection had no validation errors.");
            }
        }

        $existing = [
            'schema_version' => 1,
            'client_source' => 'EXISTING',
            'source_client_id' => fake()->uuid(),
            'client' => ['government_id' => 'forged'],
        ];
        $this->expectException(ValidationException::class);
        CaseDraftPayload::fromArray($existing);
    }

    public function test_publish_rejects_an_existing_client_with_incomplete_canonical_profile_or_address(): void
    {
        $owner = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $client = Client::factory()->create(['sex' => null]);
        $draft = app(CaseDraftService::class)->create([
            'schema_version' => 1,
            'client_source' => 'EXISTING',
            'source_client_id' => $client->id,
            'client_type' => 'OFW',
            'category_ids' => [$category->id],
        ], $owner->id);

        $this->expectException(ValidationException::class);
        app(CaseDraftService::class)->publish($draft, $owner->id, 1);
    }

    public function test_envelope_version_and_key_failures_are_rejected(): void
    {
        $encryptor = app(VersionedPayloadEncryptor::class);

        foreach ([
            json_encode(['v' => 2, 'kid' => 'current', 'ct' => 'ciphertext']),
            json_encode(['v' => 1, 'kid' => 'unknown-key', 'ct' => 'ciphertext']),
        ] as $envelope) {
            try {
                $encryptor->decrypt($envelope);
                $this->fail('Invalid encryption envelope was accepted.');
            } catch (CaseDraftPayloadDecryptionException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_old_key_payloads_are_read_and_reencrypted_with_bounded_resume(): void
    {
        $oldKey = 'base64:'.base64_encode(random_bytes(32));
        $currentKey = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $oldKey, 'encryption.key_id' => 'old-key', 'app.previous_keys' => [], 'encryption.previous_key_ids' => []]);

        $owner = User::factory()->create();
        $service = app(CaseDraftService::class);
        $first = $service->create($this->newPayload(), $owner->id);
        $second = $service->create($this->newPayload(), $owner->id);

        config([
            'app.key' => $currentKey,
            'app.previous_keys' => [$oldKey],
            'encryption.key_id' => 'current-key',
            'encryption.previous_key_ids' => ['old-key'],
        ]);

        $ordered = CaseDraft::whereIn('id', [$first->id, $second->id])->orderBy('id')->pluck('id')->values();
        $this->assertSame('NEW', CaseDraft::findOrFail($ordered[0])->payload_encrypted['client_source']);

        $this->artisan('case-drafts:reencrypt', ['--limit' => 1])->assertSuccessful();
        $this->assertTrue(app(VersionedPayloadEncryptor::class)->isCurrentKey((string) CaseDraft::findOrFail($ordered[0])->getRawOriginal('payload_encrypted')));
        $this->assertFalse(app(VersionedPayloadEncryptor::class)->isCurrentKey((string) CaseDraft::findOrFail($ordered[1])->getRawOriginal('payload_encrypted')));

        $this->artisan('case-drafts:reencrypt', ['--after' => $ordered[0], '--limit' => 1])->assertSuccessful();
        $this->assertTrue(app(VersionedPayloadEncryptor::class)->isCurrentKey((string) CaseDraft::findOrFail($ordered[1])->getRawOriginal('payload_encrypted')));
    }

    public function test_enabled_api_denies_agency_admin_and_other_owner(): void
    {
        config(['features.case_drafts.enabled' => true]);
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $draft = app(CaseDraftService::class)->create($this->newPayload(), $owner->id);

        foreach (['AGENCY', 'ADMIN'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->putJson(route('case-drafts.update', $draft), [
                    'expected_revision' => 1,
                    ...$this->newPayload(),
                ])->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => 'CASE_MANAGER']))
            ->postJson(route('case-drafts.publish', $draft), ['expected_revision' => 1])
            ->assertForbidden();
    }

    public function test_case_draft_rls_and_check_constraints_are_installed(): void
    {
        $constraints = collect(DB::select(
            "SELECT conname FROM pg_constraint WHERE conrelid = 'case_drafts'::regclass"
        ))->pluck('conname')->all();
        $this->assertContains('case_drafts_revision_check', $constraints);
        $this->assertContains('case_drafts_state_check', $constraints);
        $this->assertContains('case_drafts_published_check', $constraints);
        $this->assertContains('case_drafts_editing_case_check', $constraints);
        $this->assertContains('case_drafts_terminal_payload_check', $constraints);
        $this->assertContains('case_drafts_consent_notice_check', $constraints);
        $this->assertContains('case_drafts_nok_evidence_check', $constraints);

        $rls = DB::selectOne(
            "SELECT relrowsecurity FROM pg_class WHERE oid = 'case_drafts'::regclass"
        );
        $this->assertTrue((bool) $rls->relrowsecurity);
        $this->assertSame(1, DB::table('pg_policies')->where('tablename', 'case_drafts')->where('policyname', 'case_drafts_owner_all')->count());
    }

    public function test_raw_database_payload_is_ciphertext_and_not_a_recognizable_payload(): void
    {
        $owner = User::factory()->create();
        $draft = app(CaseDraftService::class)->create($this->newPayload(), $owner->id);
        $raw = DB::table('case_drafts')->where('id', $draft->id)->value('payload_encrypted');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('new@example.test', $raw);
        $this->assertStringNotContainsString('"client_source":"NEW"', $raw);
        $this->assertSame($this->newPayload(), $draft->refresh()->payload_encrypted);
    }

    public function test_payload_decryption_and_unknown_key_fail_closed(): void
    {
        $encryptor = app(VersionedPayloadEncryptor::class);

        foreach (['not-json', json_encode(['v' => 1, 'kid' => 'unknown', 'ct' => 'invalid'])] as $ciphertext) {
            try {
                $encryptor->decrypt($ciphertext);
                $this->fail('Invalid draft ciphertext was accepted.');
            } catch (CaseDraftPayloadDecryptionException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }

        $owner = User::factory()->create();
        $draft = app(CaseDraftService::class)->create($this->newPayload(), $owner->id);
        DB::table('case_drafts')->where('id', $draft->id)->update(['payload_encrypted' => 'tampered']);
        $draft->refresh();

        $this->expectException(CaseDraftPayloadDecryptionException::class);
        $draft->payload_encrypted;
    }

    public function test_payload_is_normalized_to_whitelisted_fields_and_enforces_bounds(): void
    {
        $payload = $this->newPayload();
        $payload['unknown'] = 'drop me';
        $payload['client']['malicious'] = ['nested' => 'drop me'];
        $payload['address']['unexpected'] = 'drop me';
        $payload['consent']['admin'] = true;
        $payload['next_of_kin'] = [[
            'id' => fake()->uuid(), 'first_name' => 'NOK', 'last_name' => 'One',
            'relationship' => 'Sibling', 'malicious' => ['x' => 'drop me'],
        ]];

        $normalized = CaseDraftPayload::fromArray($payload)->toArray();
        $this->assertArrayNotHasKey('unknown', $normalized);
        $this->assertArrayNotHasKey('malicious', $normalized['client']);
        $this->assertArrayNotHasKey('unexpected', $normalized['address']);
        $this->assertArrayNotHasKey('admin', $normalized['consent']);
        $this->assertArrayNotHasKey('malicious', $normalized['next_of_kin'][0]);

        $this->expectException(ValidationException::class);
        CaseDraftPayload::fromArray(array_merge($this->newPayload(), ['summary' => str_repeat('x', 5001)]));
    }

    public function test_save_accepts_a_draft_but_publish_requires_strict_completeness(): void
    {
        $owner = User::factory()->create();
        $service = app(CaseDraftService::class);
        $draft = $service->create(['schema_version' => 1, 'client_source' => 'NEW'], $owner->id);
        $saved = $service->save($draft, ['schema_version' => 1, 'client_source' => 'NEW'], $owner->id, 1);
        $this->assertSame(2, $saved->revision);

        $this->expectException(ValidationException::class);
        $service->publish($saved, $owner->id, 2);
    }

    public function test_only_the_owner_can_save_a_draft(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $service = app(CaseDraftService::class);
        $draft = $service->create($this->newPayload(), $owner->id);

        $this->expectException(AuthorizationException::class);
        $service->save($draft, $this->newPayload(), $other->id, 1);
    }

    public function test_save_replaces_the_full_payload_and_clears_omitted_fields(): void
    {
        $owner = User::factory()->create();
        $service = app(CaseDraftService::class);
        $draft = $service->create(array_merge($this->newPayload(), ['summary' => 'Old']), $owner->id);

        $replacement = $this->newPayload();
        unset($replacement['summary'], $replacement['employment']);
        $saved = $service->save($draft, $replacement, $owner->id, 1);

        $this->assertSame(2, $saved->revision);
        $this->assertArrayNotHasKey('summary', $saved->payload_encrypted);
        $this->assertArrayNotHasKey('employment', $saved->payload_encrypted);
        $this->assertSame($replacement, $saved->payload_encrypted);
    }

    public function test_stale_revision_returns_409(): void
    {
        $owner = User::factory()->create();
        $service = app(CaseDraftService::class);
        $draft = $service->create($this->newPayload(), $owner->id);

        try {
            $service->save($draft, $this->newPayload(), $owner->id, 9);
            $this->fail('A stale revision was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_discard_increments_state_and_clears_payload_with_delete_audit(): void
    {
        $owner = User::factory()->create();
        $service = app(CaseDraftService::class);
        $draft = $service->create($this->newPayload(), $owner->id);
        $discarded = $service->discard($draft, $owner->id, 1);

        $this->assertSame(CaseDraft::STATE_DISCARDED, $discarded->state);
        $this->assertNull($discarded->payload_encrypted);
        $this->assertNotNull($discarded->discarded_at);
        $this->assertDatabaseHas('audit_logs', [
            'entity_id' => $draft->id,
            'module' => 'case_draft',
            'action' => 'DELETE',
        ]);
    }

    public function test_terminal_draft_save_and_discard_return_410(): void
    {
        $owner = User::factory()->create();
        $service = app(CaseDraftService::class);
        $draft = $service->create($this->newPayload(), $owner->id);
        $discarded = $service->discard($draft, $owner->id, 1);

        try {
            $service->save($discarded, $this->newPayload(), $owner->id, 1);
            $this->fail('A discarded draft accepted a save.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }

        try {
            $service->discard($discarded, $owner->id, 1);
            $this->fail('A discarded draft accepted a second discard.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }
    }

    public function test_existing_client_profile_fields_are_rejected_with_actionable_validation(): void
    {
        $owner = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $client = Client::factory()->create(['email' => 'existing@example.test', 'sex' => 'MALE']);
        $client->addresses()->create([
            'region' => 'Region VII',
            'province' => 'Cebu',
            'city_municipality' => 'Cebu City',
            'barangay' => 'Barangay 1',
        ]);
        $payload = [
            'schema_version' => 1,
            'client_source' => 'EXISTING',
            'source_client_id' => $client->id,
            'client_type' => 'OFW',
            'category_ids' => [$category->id],
            'client' => ['first_name' => 'Forged'],
        ];
        try {
            app(CaseDraftService::class)->create($payload, $owner->id);
            $this->fail('Forged existing-client profile data was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('client', $exception->errors());
            $this->assertStringContainsString('canonical client references', $exception->errors()['client'][0]);
        }
    }

    public function test_new_client_publish_materializes_client_children(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $temporaryNokId = fake()->uuid();
        $payload = array_merge($this->newPayload(), [
            'category_ids' => [$category->id],
            'selected_nok_id' => $temporaryNokId,
            'next_of_kin' => [[
                'id' => $temporaryNokId,
                'first_name' => 'New NOK',
                'last_name' => 'Relative',
                'relationship' => 'Sibling',
                'email' => 'nok@example.test',
            ]],
        ]);
        $service = app(CaseDraftService::class);
        $case = $service->publish($service->create($payload, $owner->id), $owner->id, 1);

        $this->assertSame('OPEN', $case->status);
        $this->assertDatabaseHas('clients', ['id' => $case->client_id, 'first_name' => 'New']);
        $this->assertSame('nok@example.test', $case->client->nextOfKin()->firstOrFail()->email);

        $draft = CaseDraft::where('published_case_id', $case->id)->firstOrFail();
        $this->assertSame($case->selected_nok_id, $draft->selected_nok_id);
        $this->assertSame($case->id, $draft->selected_nok_evidence['case_id']);
        $this->assertSame($case->selected_nok_id, $draft->selected_nok_evidence['nok_id']);
        $this->assertSame('v1', $draft->consent_notice_version);
        $this->assertNotNull($draft->consent_accepted_at);
        $this->assertSame('v1', $case->consent_notice_version);
        $this->assertSame('2026-01-15 10:30:45', $case->consent_given_at->format('Y-m-d H:i:s'));
    }

    public function test_publish_is_idempotent_for_a_published_draft(): void
    {
        $owner = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $service = app(CaseDraftService::class);
        $draft = $service->create(array_merge($this->newPayload(), ['category_ids' => [$category->id]]), $owner->id);
        $first = $service->publish($draft, $owner->id, 1);
        $auditCount = AuditLog::where('entity_id', $draft->id)->where('action', 'PUBLISH')->count();
        $second = $service->publish($draft->id, $owner->id, 999);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CaseFile::whereKey($first->id)->count());
        $this->assertSame($auditCount, AuditLog::where('entity_id', $draft->id)->where('action', 'PUBLISH')->count());
    }

    public function test_draft_audits_are_metadata_only(): void
    {
        $owner = User::factory()->create();
        $service = app(CaseDraftService::class);
        $draft = $service->create(array_merge($this->newPayload(), ['summary' => 'Private payload']), $owner->id);
        $audit = AuditLog::where('entity_id', $draft->id)->where('module', 'case_draft')->firstOrFail();

        $this->assertArrayNotHasKey('payload', $audit->new_value ?? []);
        $this->assertArrayNotHasKey('summary', $audit->new_value ?? []);
    }

    public function test_command_http_status_semantics_are_exposed_for_api_lane(): void
    {
        $owner = User::factory()->create();
        $service = app(CaseDraftService::class);
        $draft = $service->create($this->newPayload(), $owner->id);

        try {
            $service->save($draft, $this->newPayload(), $owner->id, 0);
            $this->fail('Expected stale revision conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $discarded = $service->discard($draft, $owner->id, 1);
        try {
            $service->save($discarded, $this->newPayload(), $owner->id, 1);
            $this->fail('Expected terminal draft rejection.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }
    }

    private function newPayload(): array
    {
        return [
            'schema_version' => 1,
            'client_source' => 'NEW',
            'client' => [
                'first_name' => 'New',
                'last_name' => 'Client',
                'date_of_birth' => '1990-01-01',
                'sex' => 'MALE',
                'contact_number' => '09170000000',
                'email' => 'new@example.test',
            ],
            'client_type' => 'OFW',
            'address' => [
                'region' => 'Region VII',
                'province' => 'Cebu',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Barangay 1',
            ],
            'consent' => ['accepted_at' => '2026-01-15T10:30:45+00:00', 'notice_version' => 'v1'],
        ];
    }
}
