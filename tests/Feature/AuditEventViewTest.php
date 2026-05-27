<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditEventViewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CaseFile $case;

    private Referral $referral;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);

        $this->user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);

        $this->case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'CASE-'.fake()->unique()->numberBetween(1000, 9999),
            'client_type' => 'OFW',
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('????')),
            'status' => 'OPEN',
            'user_id' => $this->user->id,
        ]);

        $this->referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test referral service',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);
    }

    #[Test]
    public function viewing_case_creates_view_audit_log(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get(route('cases.show', $this->case->id));

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'VIEW',
            'module' => 'CASE',
            'entity_id' => $this->case->id,
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function viewing_referral_creates_view_audit_log(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get(route('referrals.show', $this->referral->id));

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'VIEW',
            'module' => 'REFERRAL',
            'entity_id' => $this->referral->id,
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function viewing_case_creates_one_audit_log_per_view(): void
    {
        $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get(route('cases.show', $this->case->id));

        $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get(route('cases.show', $this->case->id));

        $count = AuditLog::where('action', 'VIEW')
            ->where('module', 'CASE')
            ->where('entity_id', $this->case->id)
            ->count();

        $this->assertEquals(2, $count);
    }

    #[Test]
    public function viewing_case_logs_correct_user(): void
    {
        $otherUser = User::factory()->create(['role' => 'CASE_MANAGER']);

        $this->actingAs($otherUser)
            ->withHeader('X-Inertia', 'true')
            ->get(route('cases.show', $this->case->id));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'VIEW',
            'module' => 'CASE',
            'entity_id' => $this->case->id,
            'user_id' => $otherUser->id,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'VIEW',
            'module' => 'CASE',
            'entity_id' => $this->case->id,
            'user_id' => $this->user->id,
        ]);
    }
}
