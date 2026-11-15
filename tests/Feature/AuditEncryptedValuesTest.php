<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encrypted columns (EncryptedString / EncryptedDate) must be recorded in the
 * audit trail as their readable value, not as the ciphertext stored in the
 * column. Regression guard: the observer used to call the protected
 * Model::castAttribute() and silently fall back to the raw ciphertext.
 */
class AuditEncryptedValuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creation_records_one_audit_entry_with_the_explicit_actor(): void
    {
        $actor = User::factory()->create();
        $user = app(UserService::class)->createUser([
            'name' => 'New Staff',
            'email' => 'new-staff@example.test',
            'password' => 'P@ssw0rd!123',
            'role' => UserRole::CASE_MANAGER->value,
        ], (string) $actor->getKey());

        $entries = AuditLog::query()->where('module', 'user')->where('entity_id', $user->getKey())->get();

        $this->assertCount(1, $entries);
        $this->assertSame($actor->getKey(), $entries->first()->user_id);
        $this->assertArrayNotHasKey('audit_actor_id', $user->getAttributes());
    }

    public function test_updating_an_encrypted_date_records_the_plaintext_date(): void
    {
        $user = User::factory()->create(['date_of_birth' => '2004-08-20']);

        $user->update(['date_of_birth' => '1999-12-31']);

        $entry = AuditLog::query()
            ->where('module', 'user')
            ->where('action', AuditAction::UPDATE->value)
            ->latest('chain_seq')
            ->firstOrFail();

        $this->assertSame('1999-12-31', $entry->new_value['date_of_birth']);
        $this->assertSame('2004-08-20', $entry->old_value['date_of_birth']);
        $this->assertStringNotContainsString('eyJpdiI6', (string) $entry->description);
    }
}
