<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralMessage;
use App\Models\User;
use App\Services\ReferralMessageService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReferralLowFindingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A single attachment version group on one referral with all three deletion
     * states: live, flag-only deleted (is_deleted = true, deleted_at still
     * null), and fully soft-deleted (deleted_at set).
     *
     * @return array{referral: Referral, versionGroupId: string, kept: ReferralAttachment, flagDeleted: ReferralAttachment, softDeleted: ReferralAttachment}
     */
    private function attachmentVersionFixtures(): array
    {
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        $case = CaseFile::factory()->create(['user_id' => $caseManager->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);
        $uploader = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $versionGroupId = (string) str()->uuid();

        $kept = ReferralAttachment::create([
            'referral_id' => $referral->id,
            'file_name' => 'current.pdf',
            'file_path' => 'referrals/current.pdf',
            'file_type' => 'application/pdf',
            'size' => 1024,
            'user_id' => $uploader->id,
            'is_archived' => false,
            'version_group_id' => $versionGroupId,
        ]);

        // Flag-only deletion (is_deleted = true while deleted_at stays null):
        // the exact state that leaked into the versions query before the fix.
        $flagDeleted = ReferralAttachment::create([
            'referral_id' => $referral->id,
            'file_name' => 'removed.pdf',
            'file_path' => 'referrals/removed.pdf',
            'file_type' => 'application/pdf',
            'size' => 512,
            'user_id' => $uploader->id,
            'is_archived' => false,
            'version_group_id' => $versionGroupId,
        ]);
        $flagDeleted->is_deleted = true;
        $flagDeleted->saveQuietly();

        // Standard soft-delete via Eloquent (sets both deleted_at and is_deleted).
        $softDeleted = ReferralAttachment::create([
            'referral_id' => $referral->id,
            'file_name' => 'trashed.pdf',
            'file_path' => 'referrals/trashed.pdf',
            'file_type' => 'application/pdf',
            'size' => 256,
            'user_id' => $uploader->id,
            'is_archived' => false,
            'version_group_id' => $versionGroupId,
        ]);
        $softDeleted->delete();

        return compact('referral', 'uploader', 'versionGroupId', 'kept', 'flagDeleted', 'softDeleted');
    }

    #[Test]
    public function get_attachment_versions_excludes_soft_deleted_versions(): void
    {
        $f = $this->attachmentVersionFixtures();

        $versions = app(ReferralService::class)
            ->getAttachmentVersions($f['referral']->id, $f['versionGroupId']);

        $this->assertSame(1, $versions->count());
        $this->assertTrue($versions->contains('id', $f['kept']->id));
        $this->assertFalse($versions->contains('id', $f['flagDeleted']->id));
        $this->assertFalse($versions->contains('id', $f['softDeleted']->id));
    }

    #[Test]
    public function export_filters_preserve_zero_valued_numeric_inputs(): void
    {
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        $caseA = CaseFile::factory()->create(['user_id' => $caseManager->id]);
        $caseB = CaseFile::factory()->create(['user_id' => $caseManager->id]);
        Referral::factory()->create(['case_id' => $caseA->id, 'agcy_id' => $agency->id]);
        Referral::factory()->create(['case_id' => $caseB->id, 'agcy_id' => $agency->id]);

        // The live row count with zero-valued age filters must match the
        // unfiltered count: a bare array_filter would have dropped both inputs.
        $this->actingAsWithMfa($caseManager)
            ->get(route('referrals.export-count', ['age_min_days' => 0, 'age_max_days' => 0]))
            ->assertOk()
            ->assertJsonPath('count', 2);

        // The download with the same zero-valued filters still streams a
        // workbook containing every row.
        $response = $this->actingAsWithMfa($caseManager)
            ->get(route('referrals.export-excel', ['age_min_days' => 0, 'age_max_days' => 0]));

        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'ref-export').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $cells = [];
        foreach (IOFactory::load($path)->getActiveSheet()->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = (string) $cell->getValue();
            }
        }
        unlink($path);

        $this->assertContains($caseA->case_number, $cells);
        $this->assertContains($caseB->case_number, $cells);
    }

    /**
     * A case with two agencies, X and Y, each holding one referral. The viewer
     * is an AGENCY user from X without any read markers.
     */
    private function messageFixtures(): array
    {
        $agencyX = Agency::factory()->create();
        $agencyY = Agency::factory()->create();
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['user_id' => $caseManager->id]);

        $referralX = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agencyX->id]);
        $referralY = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agencyY->id]);

        $viewer = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agencyX->id, 'is_active' => true]);
        $colleague = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agencyX->id, 'is_active' => true]);
        $peer = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agencyY->id, 'is_active' => true]);

        return compact('referralX', 'referralY', 'viewer', 'colleague', 'peer');
    }

    #[Test]
    public function own_agency_messages_never_count_as_unread_for_the_viewer(): void
    {
        $f = $this->messageFixtures();

        // The viewer's own agency wrote the thread's latest message (anchored on
        // the peer's card). This must never badge as unread for any X viewer,
        // including one who holds no read marker of their own.
        ReferralMessage::create([
            'referral_id' => $f['referralY']->id,
            'sender_user_id' => $f['colleague']->id,
            'body' => 'Update posted by our own agency.',
        ]);

        $counts = app(ReferralMessageService::class)
            ->unreadCountsByReferral([$f['referralX']->id, $f['referralY']->id], $f['viewer']);

        $this->assertSame(0, $counts[$f['referralX']->id] ?? 0);
        $this->assertSame(0, $counts[$f['referralY']->id] ?? 0);

        // A message sent by the peer agency still counts as unread: anchored on
        // the viewer's referral (the shared X<->Y pair thread).
        ReferralMessage::create([
            'referral_id' => $f['referralX']->id,
            'sender_user_id' => $f['peer']->id,
            'body' => 'Question from the partner agency.',
        ]);

        $counts = app(ReferralMessageService::class)
            ->unreadCountsByReferral([$f['referralX']->id, $f['referralY']->id], $f['viewer']);

        $this->assertSame(1, $counts[$f['referralY']->id] ?? 0);
        $this->assertSame(0, $counts[$f['referralX']->id] ?? 0);
    }

    #[Test]
    public function foreign_related_referral_exposes_only_shared_metadata(): void
    {
        $f = $this->messageFixtures();
        $f['referralY']->update([
            'concern' => 'private concern',
            'override_reason' => 'private override',
            'notes' => 'private notes',
            'decision_comment' => 'private decision',
        ]);
        $f['referralY']->refresh();
        $this->assertSame('private notes', $f['referralY']->notes);
        $milestone = app(ReferralService::class)->addMilestone($f['referralY']->id, 'private milestone title', 'private milestone description', $f['peer']->id);

        $related = app(ReferralService::class)->getRelatedReferrals($f['referralX'], $f['viewer'])->sole();
        $serialized = $related->toArray();

        foreach (['concern', 'override_reason', 'notes', 'decision_comment'] as $privateField) {
            $this->assertArrayNotHasKey($privateField, $serialized);
        }
        $this->assertArrayHasKey('agency', $serialized);
        $this->assertArrayHasKey('services', $serialized);
        $this->assertArrayHasKey('can_message', $serialized);
        $this->assertStringNotContainsString('private milestone title', json_encode($serialized));
        $this->assertStringNotContainsString('private milestone description', json_encode($serialized));
    }

    #[Test]
    public function replacing_an_already_archived_attachment_rejects_stale_write(): void
    {
        $f = $this->attachmentVersionFixtures();
        $f['kept']->update(['is_archived' => true]);

        try {
            app(ReferralService::class)->replaceAttachment($f['referral']->id, $f['kept']->id, [
                'name' => 'stale.pdf', 'path' => 'referrals/stale.pdf', 'type' => 'application/pdf', 'size' => 10,
            ], $f['uploader']->id);
            $this->fail('Archived attachment replacement must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertDatabaseMissing('referral_attachments', ['file_name' => 'stale.pdf']);
    }
}
