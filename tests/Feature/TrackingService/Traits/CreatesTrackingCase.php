<?php

namespace Tests\Feature\TrackingService\Traits;

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait CreatesTrackingCase
{
    /**
     * Create a complete CaseFile with Client, User, N referrals, and N milestones per referral.
     *
     * @return array{case: CaseFile, client: Client, referrals: Collection, milestones: Collection, user: User}
     */
    public function createCompleteCase(int $referralCount = 1, int $milestonesPerReferral = 0): array
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
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
            'client' => $client,
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
