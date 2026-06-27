<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserModelHiddenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function mfa_secret_and_recovery_codes_are_hidden_from_array(): void
    {
        $user = User::factory()->create([
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_recovery_codes' => [
                'recovery-code-1',
                'recovery-code-2',
            ],
        ]);

        $data = $user->toArray();

        $this->assertArrayNotHasKey('mfa_secret', $data);
        $this->assertArrayNotHasKey('mfa_recovery_codes', $data);
    }

    #[Test]
    public function make_visible_can_override_hidden_mfa_secret(): void
    {
        $user = User::factory()->create([
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_recovery_codes' => [
                'recovery-code-1',
                'recovery-code-2',
            ],
        ]);

        $data = $user->makeVisible(['mfa_secret'])->toArray();

        $this->assertArrayHasKey('mfa_secret', $data);
        $this->assertEquals('JBSWY3DPEHPK3PXP', $data['mfa_secret']);
        $this->assertArrayNotHasKey('mfa_recovery_codes', $data);
    }
}
