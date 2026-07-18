<?php

namespace Tests\Feature;

use App\Encryption\CaseDraftReencryptionService;
use App\Encryption\VersionedPayloadEncryptor;
use App\Exceptions\CaseDraftPayloadDecryptionException;
use App\Models\CaseDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CaseDraftReencryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_ciphertext_snapshot_is_skipped(): void
    {
        $draft = $this->draft();
        $snapshot = $this->raw($draft);
        DB::table('case_drafts')->where('id', $draft->id)->update([
            'payload_encrypted' => app(VersionedPayloadEncryptor::class)->encrypt(['changed' => true]),
        ]);
        $changed = $this->raw($draft->refresh());

        $this->assertFalse(app(CaseDraftReencryptionService::class)->reencryptIfUnchanged($draft, $snapshot));
        $this->assertSame($changed, $this->raw($draft->refresh()));
    }

    public function test_reencryption_preserves_updated_at(): void
    {
        $oldKey = $this->key();
        $currentKey = $this->key();
        config(['app.key' => $oldKey, 'encryption.key_id' => 'old-key', 'app.previous_keys' => []]);
        $draft = $this->draft();
        $snapshot = $this->raw($draft);
        $originalUpdatedAt = '2026-01-01 00:00:00';
        DB::table('case_drafts')->where('id', $draft->id)->update(['updated_at' => $originalUpdatedAt]);

        config(['app.key' => $currentKey, 'encryption.key_id' => 'current-key', 'app.previous_keys' => [$oldKey], 'encryption.previous_key_ids' => ['old-key']]);
        $this->assertTrue(app(CaseDraftReencryptionService::class)->reencryptIfUnchanged($draft, $snapshot));
        $this->assertSame($originalUpdatedAt, DB::table('case_drafts')->where('id', $draft->id)->value('updated_at'));
        $this->assertTrue(app(VersionedPayloadEncryptor::class)->isCurrentKey($this->raw($draft->refresh())));
    }

    public function test_non_editing_draft_is_not_reencrypted(): void
    {
        $draft = $this->draft();
        $snapshot = $this->raw($draft);
        DB::table('case_drafts')->where('id', $draft->id)->update([
            'state' => CaseDraft::STATE_DISCARDED,
            'payload_encrypted' => null,
            'discarded_at' => now(),
        ]);

        $this->assertFalse(app(CaseDraftReencryptionService::class)->reencryptIfUnchanged($draft, $snapshot));
        $this->assertNull(DB::table('case_drafts')->where('id', $draft->id)->value('payload_encrypted'));
    }

    public function test_old_key_payload_is_read_and_reencrypted_with_current_key(): void
    {
        $oldKey = $this->key();
        $currentKey = $this->key();
        config(['app.key' => $oldKey, 'encryption.key_id' => 'old-key', 'app.previous_keys' => [], 'encryption.previous_key_ids' => []]);
        $draft = $this->draft(['marker' => 'old-key-payload']);
        $snapshot = $this->raw($draft);

        config(['app.key' => $currentKey, 'encryption.key_id' => 'current-key', 'app.previous_keys' => [$oldKey], 'encryption.previous_key_ids' => ['old-key']]);
        $this->assertSame('old-key-payload', $draft->refresh()->payload_encrypted['marker']);
        $this->assertTrue(app(CaseDraftReencryptionService::class)->reencryptIfUnchanged($draft, $snapshot));
        $this->assertTrue(app(VersionedPayloadEncryptor::class)->isCurrentKey($this->raw($draft->refresh())));
    }

    public function test_command_resumes_after_the_last_processed_uuid(): void
    {
        $oldKey = $this->key();
        $currentKey = $this->key();
        config(['app.key' => $oldKey, 'encryption.key_id' => 'old-key', 'app.previous_keys' => []]);
        $first = $this->draft(['position' => 1]);
        $second = $this->draft(['position' => 2]);
        $ordered = CaseDraft::whereIn('id', [$first->id, $second->id])->orderBy('id')->pluck('id')->values();

        config(['app.key' => $currentKey, 'encryption.key_id' => 'current-key', 'app.previous_keys' => [$oldKey], 'encryption.previous_key_ids' => ['old-key']]);
        $this->artisan('case-drafts:reencrypt', ['--limit' => 1])->assertSuccessful();
        $this->assertTrue(app(VersionedPayloadEncryptor::class)->isCurrentKey($this->raw(CaseDraft::findOrFail($ordered[0]))));
        $this->assertFalse(app(VersionedPayloadEncryptor::class)->isCurrentKey($this->raw(CaseDraft::findOrFail($ordered[1]))));

        $this->artisan('case-drafts:reencrypt', ['--after' => $ordered[0], '--limit' => 1])->assertSuccessful();
        $this->assertTrue(app(VersionedPayloadEncryptor::class)->isCurrentKey($this->raw(CaseDraft::findOrFail($ordered[1]))));
    }

    public function test_invalid_envelope_version_fails_closed(): void
    {
        $this->expectException(CaseDraftPayloadDecryptionException::class);
        app(VersionedPayloadEncryptor::class)->decrypt(json_encode(['v' => 2, 'kid' => 'current-key', 'ct' => 'ciphertext']));
    }

    private function draft(array $payload = []): CaseDraft
    {
        return CaseDraft::create([
            'owner_id' => User::factory()->create()->id,
            'payload_encrypted' => $payload ?: ['marker' => 'payload'],
            'payload_schema_version' => 1,
            'revision' => 1,
            'state' => CaseDraft::STATE_EDITING,
        ]);
    }

    private function raw(CaseDraft $draft): string
    {
        return (string) $draft->getRawOriginal('payload_encrypted');
    }

    private function key(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }
}
