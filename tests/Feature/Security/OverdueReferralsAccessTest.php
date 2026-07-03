<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OverdueReferralsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function case_manager_can_access_overdue_referrals(): void
    {
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($cm)->get(route('overdue-referrals.index'));

        $response->assertOk();
    }

    #[Test]
    public function admin_can_access_overdue_referrals(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->get(route('overdue-referrals.index'));

        $response->assertOk();
    }
}
