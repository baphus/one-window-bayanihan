<?php

namespace Tests\Feature\TrackingService\Traits;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait CreatesTrackingCase
{
    /**
     * Create a complete CaseFile with case-owned intake, User, N referrals, and N milestones per referral.
     *
     * @return array{case: CaseFile, referrals: Collection, milestones: Collection, user: User}
     */
    public function createCompleteCase(int $referralCount = 1, int $milestonesPerReferral = 0): array
    {
        $user = User::factory()->create();
        $case = CaseFile::factory()->withIntake()->create([
            'user_id' => $user->id,
            'ofw_user_id' => User::factory()->create(['role' => UserRole::OFW->value])->id,
        ]);

        $referrals = collect();
        $allMilestones = collect();

        for ($i = 0; $i < $referralCount; $i++) {
            $referral = Referral::factory()->create([
                'case_id' => $case->id,
                'status' => 'PENDING',
            ]);
            $referrals->push($referral);

            for ($m = 0; $m < $milestonesPerReferral; $m++) {
                $milestone = Milestone::factory()->create([
                    'refr_id' => $referral->id,
                ]);
                $allMilestones->push($milestone);
            }
        }

        return [
            'case' => $case,
            'referrals' => $referrals,
            'milestones' => $allMilestones,
            'user' => $user,
        ];
    }

    /**
     * Create a Referral on the given case with N milestones and an Agency.
     */
    public function createReferralWithMilestones(CaseFile $case, int $milestoneCount = 1, string $status = 'PENDING'): Referral
    {
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => $status,
        ]);

        for ($i = 0; $i < $milestoneCount; $i++) {
            Milestone::factory()->create([
                'refr_id' => $referral->id,
            ]);
        }

        return $referral;
    }

    /**
     * Create an AuditLog record manually (no factory exists).
     */
    public function createAuditLog(
        string $entityId,
        string $action = 'CREATE',
        string $module = 'case_files',
        ?array $oldValue = null,
        ?array $newValue = null,
        ?string $description = null,
        ?string $userId = null
    ): AuditLog {
        return AuditLog::create([
            'entity_id' => $entityId,
            'action' => $action,
            'module' => $module,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
            'user_id' => $userId,
            'timestamp' => now(),
        ]);
    }

    /**
     * Build a tracker number using the standard OWBAP format.
     */
    public function buildTrackerNumber(): string
    {
        return 'OWBAP-'.strtoupper(Str::random(7));
    }
}
