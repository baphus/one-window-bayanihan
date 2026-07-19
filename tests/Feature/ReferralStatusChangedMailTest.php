<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\User;
use App\Notifications\ReferralStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralStatusChangedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_rendered_status_transition_is_humanized_and_uses_an_arrow_not_an_html_entity(): void
    {
        $user = User::factory()->create(['email' => 'recipient@example.com']);
        $referral = Referral::factory()->create([
            'required_services' => 'Passport assistance',
        ]);

        $html = (new ReferralStatusChanged($referral, 'PENDING', 'FOR_COMPLIANCE'))
            ->toMail($user)
            ->render();

        $this->assertStringContainsString('Pending', $html);
        $this->assertStringContainsString('For Compliance', $html);
        $this->assertStringContainsString('→', $html);
        $this->assertStringNotContainsString('&rarr;', $html);
        $this->assertStringNotContainsString('PENDING', $html);
        $this->assertStringNotContainsString('FOR_COMPLIANCE', $html);
    }
}
