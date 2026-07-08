<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CaseReferralGuardTest extends TestCase
{
    use RefreshDatabase;

    private CaseService $caseService;

    private User $user;

    private CaseFile $case;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->caseService = app(CaseService::class);
        $this->user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);

        $this->case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'client_type' => 'OFW',
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'status' => 'OPEN',
            'user_id' => $this->user->id,
        ]);
    }

    private function createReferral(string $status): Referral
    {
        return Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => $status,
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);
    }

    #[Test]
    public function can_close_returns_true_when_no_referrals(): void
    {
        $result = $this->caseService->canClose($this->case->id);

        $this->assertTrue($result['can_close']);
        $this->assertNull($result['reason']);
    }

    #[Test]
    public function can_close_returns_true_when_all_referrals_completed(): void
    {
        $this->createReferral('COMPLETED');
        $this->createReferral('REJECTED');

        $result = $this->caseService->canClose($this->case->id);

        $this->assertTrue($result['can_close']);
        $this->assertNull($result['reason']);
    }

    #[Test]
    public function can_close_returns_false_when_referral_pending(): void
    {
        $this->createReferral('PENDING');

        $result = $this->caseService->canClose($this->case->id);

        $this->assertFalse($result['can_close']);
        $this->assertStringContainsString('pending', strtolower($result['reason']));
    }

    #[Test]
    public function can_close_returns_false_when_referral_processing(): void
    {
        $this->createReferral('PROCESSING');

        $result = $this->caseService->canClose($this->case->id);

        $this->assertFalse($result['can_close']);
        $this->assertNotNull($result['reason']);
    }

    #[Test]
    public function toggle_case_status_closes_case_when_open_and_no_active_referrals(): void
    {
        $updated = $this->caseService->toggleCaseStatus($this->case->id, $this->user->id);

        $this->assertEquals('CLOSED', $updated->status);
    }

    #[Test]
    public function toggle_case_status_throws_when_open_with_pending_referral(): void
    {
        $this->createReferral('PENDING');

        try {
            $this->caseService->toggleCaseStatus($this->case->id, $this->user->id);
            $this->fail('Expected HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }
    }

    #[Test]
    public function toggle_case_status_reopens_closed_case_without_checking_referrals(): void
    {
        $this->case->update(['status' => 'CLOSED']);
        $this->createReferral('PENDING');

        // Re-opening a closed case should not check referrals
        $updated = $this->caseService->toggleCaseStatus($this->case->id, $this->user->id);

        $this->assertEquals('OPEN', $updated->status);
    }

    #[Test]
    public function archive_case_throws_when_not_closed(): void
    {
        try {
            $this->caseService->archiveCase($this->case->id, $this->user->id);
            $this->fail('Expected HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }
    }

    #[Test]
    public function archive_case_throws_when_closed_with_pending_referral(): void
    {
        $this->case->update(['status' => 'CLOSED']);
        $this->createReferral('PENDING');

        try {
            $this->caseService->archiveCase($this->case->id, $this->user->id);
            $this->fail('Expected HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }
    }

    #[Test]
    public function archive_case_succeeds_when_closed_and_no_active_referrals(): void
    {
        $this->case->update(['status' => 'CLOSED', 'closed_at' => now()]);

        $updated = $this->caseService->archiveCase($this->case->id, $this->user->id);

        $this->assertEquals('ARCHIVED', $updated->status);
    }

    #[Test]
    public function toggle_case_status_from_open_creates_audit_log(): void
    {
        $this->caseService->toggleCaseStatus($this->case->id, $this->user->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE',
            'module' => 'case',
            'entity_id' => $this->case->id,
        ]);
    }
}
