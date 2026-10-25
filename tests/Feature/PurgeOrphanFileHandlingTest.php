<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\GeneratedDocument;
use App\Models\Referral;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientMessageAttachment;
use App\Models\ReferralClientRequest;
use App\Models\User;
use App\Services\CaseService;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The two remaining file-lifecycle media.
 *
 * M1: cases:purge-trashed force-deletes the rows that point at a case's stored
 * files, so storage:cleanup-orphans — which keys on those rows — can never reach
 * the objects afterwards. A purge that lands before a successful sweep therefore
 * leaked them permanently. The purge itself has to empty the bucket.
 *
 * M2: generated-documents/ objects had no lifecycle at all. The row has no soft
 * delete, and a case purge only SET NULLs its case_id, so an admin workbook sat
 * in the bucket forever.
 *
 * M3 (verified): the export trigger is ADMIN-only (routes/web.php:251,304-305)
 * and no route streams a generated document at all — GeneratedDocument has no
 * fileUrl() accessor, so no signed URL is ever minted for one. The only
 * byte-serving surface is files/temp/{disk}/{path}, behind `signed`.
 */
class PurgeOrphanFileHandlingTest extends TestCase
{
    use RefreshDatabase;

    private const RETENTION_DAYS = 90;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filesystems.default', 'object-storage');
        Config::set('app.trash_retention_days', self::RETENTION_DAYS);
        Storage::fake('object-storage');
        $this->app->forgetInstance(StorageService::class);

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($this->admin);
    }

    public function test_purging_a_trashed_case_deletes_the_objects_behind_it(): void
    {
        $case = $this->purgableCaseWithFiles();

        Storage::disk('object-storage')->assertExists('case-files/purge-me.pdf');
        Storage::disk('object-storage')->assertExists('attachments/referral.pdf');
        Storage::disk('object-storage')->assertExists('client-request-attachments/passport.pdf');

        $this->artisan('cases:purge-trashed', ['--days' => self::RETENTION_DAYS])->assertSuccessful();

        $this->assertNull(CaseFile::withTrashed()->find($case->id), 'the case row should be gone');
        Storage::disk('object-storage')->assertMissing('case-files/purge-me.pdf');
        Storage::disk('object-storage')->assertMissing('attachments/referral.pdf');
        Storage::disk('object-storage')->assertMissing('client-request-attachments/passport.pdf');
    }

    public function test_purge_still_succeeds_when_deleting_the_objects_fails(): void
    {
        $case = $this->purgableCaseWithFiles();

        $storage = $this->createMock(StorageService::class);
        $storage->method('delete')->willThrowException(new \RuntimeException('bucket unavailable'));
        $this->instance(StorageService::class, $storage);

        $this->artisan('cases:purge-trashed', ['--days' => self::RETENTION_DAYS])
            ->expectsOutputToContain('Purged 1 case(s)')
            ->assertExitCode(0);

        // The purge committed; only the objects are orphaned, and that is logged
        // rather than allowed to abort the batch.
        $this->assertNull(CaseFile::withTrashed()->find($case->id));
        Storage::disk('object-storage')->assertExists('case-files/purge-me.pdf');
    }

    public function test_sweep_retires_generated_documents_past_the_retention_window(): void
    {
        $expiredPath = $this->generatedDocument('expired-export.xlsx', days: self::RETENTION_DAYS + 10);
        $keptPath = $this->generatedDocument('fresh-export.xlsx', days: 2);

        Storage::disk('object-storage')->put($expiredPath, 'xlsx-bytes');
        Storage::disk('object-storage')->put($keptPath, 'xlsx-bytes');

        $this->artisan('storage:cleanup-orphans')->assertSuccessful();

        Storage::disk('object-storage')->assertMissing($expiredPath);
        Storage::disk('object-storage')->assertExists($keptPath);

        $expired = GeneratedDocument::where('filename', 'expired-export.xlsx')->first();

        $this->assertNotNull($expired);
        $this->assertTrue($expired->isExpired());
        $this->assertNull($expired->path);

        // Self-stamping: the second run has nothing left to do.
        $this->artisan('storage:cleanup-orphans')
            ->expectsOutputToContain('Cleaned up 0 orphaned files.')
            ->assertSuccessful();
    }

    public function test_sweep_retrieves_the_object_a_failed_export_left_behind(): void
    {
        $path = $this->generatedDocument('failed-export.xlsx', days: self::RETENTION_DAYS + 10, status: 'failed');

        // GenerateSystemReport writes the object before it records the path, so
        // a failed run can leave bytes that no column points at.
        Storage::disk('object-storage')->put($path, 'partial-bytes');

        $this->artisan('storage:cleanup-orphans')->assertSuccessful();

        Storage::disk('object-storage')->assertMissing($path);
        $this->assertTrue(GeneratedDocument::where('filename', 'failed-export.xlsx')->first()->isExpired());
    }

    public function test_generated_document_objects_are_not_streamed_without_a_signed_link(): void
    {
        $path = $this->generatedDocument('secret-export.xlsx', days: 1);

        Storage::disk('object-storage')->put($path, 'xlsx-bytes');

        $url = '/files/temp/object-storage/'.$path;

        // Anonymous: refused by the `signed` middleware (routes/web.php:442-445).
        $this->get($url)->assertForbidden();

        // An authenticated non-admin is refused too — there is no role that
        // unlocks an unsigned path, because the signature is the capability.
        $this->actingAs(User::factory()->create(['role' => UserRole::CASE_MANAGER->value]));
        $this->get($url)->assertForbidden();

        $this->actingAs($this->admin);
        $this->get($url)->assertForbidden();
    }

    /**
     * A trashed case past the retention cutoff holding one object on each of the
     * three chains the purge has to empty.
     */
    private function purgableCaseWithFiles(): CaseFile
    {
        $service = app(CaseService::class);

        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $this->admin->id]);
        $service->deleteArchivedCase($case, 'Archived case awaiting the retention purge', $this->admin->id);
        CaseFile::withTrashed()->where('id', $case->id)->update(['deleted_at' => now()->subDays(self::RETENTION_DAYS + 10)]);

        CaseDocument::create([
            'file_name' => 'purge-me.pdf',
            'file_path' => 'case-files/purge-me.pdf',
            'file_type' => 'application/pdf',
            'case_id' => $case->id,
            'user_id' => $this->admin->id,
        ]);

        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'COMPLETED']);
        $referral->attachments()->create([
            'file_name' => 'referral.pdf',
            'file_path' => 'attachments/referral.pdf',
            'file_type' => 'application/pdf',
            'user_id' => $this->admin->id,
        ]);

        $request = ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'creator_user_id' => $this->admin->id,
        ]);
        $message = ReferralClientMessage::factory()->create([
            'request_id' => $request->id,
            'sender_kind' => ReferralClientMessage::SENDER_AGENCY_USER,
            'user_id' => $this->admin->id,
        ]);

        // The public tracking-link upload medium.
        ReferralClientMessageAttachment::create([
            'message_id' => $message->id,
            'file_name' => 'passport.pdf',
            'file_path' => 'client-request-attachments/passport.pdf',
            'file_type' => 'application/pdf',
            'size' => 9,
        ]);

        foreach ([
            'case-files/purge-me.pdf',
            'attachments/referral.pdf',
            'client-request-attachments/passport.pdf',
        ] as $path) {
            Storage::disk('object-storage')->put($path, 'bytes');
        }

        return $case;
    }

    /**
     * A generated export row aged by the given number of days, returning the
     * object key the job would have written.
     */
    private function generatedDocument(string $filename, int $days, string $status = 'completed'): string
    {
        $document = GeneratedDocument::create([
            'user_id' => $this->admin->id,
            'type' => 'admin_full_export',
            'filename' => $filename,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'status' => $status,
        ]);

        if ($status === 'completed') {
            $document->update([
                'path' => $document->storagePath(),
                'file_size' => 10,
            ]);
        }

        GeneratedDocument::withoutTimestamps(fn () => GeneratedDocument::whereKey($document->id)
            ->update(['created_at' => now()->subDays($days)]));

        return $document->storagePath();
    }
}
