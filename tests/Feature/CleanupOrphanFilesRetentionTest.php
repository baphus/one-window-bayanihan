<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientRequest;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Retention alignment for storage:cleanup-orphans.
 *
 * The sweep used to key its one-day grace off the file's own row, so a case
 * trashed yesterday and restored today came back with its documents intact as
 * rows and empty in the bucket. It must now key off the parent case instead,
 * and it must actually reach client-request message attachments — the files a
 * citizen uploads through a public tracking link.
 */
class CleanupOrphanFilesRetentionTest extends TestCase
{
    use RefreshDatabase;

    private const RETENTION_DAYS = 90;

    private User $user;

    private CaseFile $case;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filesystems.default', 'object-storage');
        Config::set('app.trash_retention_days', self::RETENTION_DAYS);
        Storage::fake('object-storage');
        $this->app->forgetInstance(StorageService::class);

        $this->user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'status' => 'OPEN',
            'user_id' => $this->user->id,
        ]);

        $this->agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Cleanup Retention Agency',
            'short' => 'CRA',
            'slug' => 'cleanup-retention-agency',
        ]);
    }

    public function test_document_on_a_case_inside_the_restore_window_keeps_its_file(): void
    {
        $path = 'case-files/still-restorable.pdf';
        Storage::disk('object-storage')->put($path, '%PDF-1.4');

        $document = $this->caseDocument($path);

        // Trashing the case cascades onto the document; backdate both so the
        // row clears the one-day grace while the case is still restorable.
        $this->case->delete();
        $this->backdate($document, 'case_documents', days: 2);
        $this->backdate($this->case, 'cases', days: 2);

        $this->assertTrue($document->fresh()->trashed(), 'the document should have followed the case into the trash');

        $this->artisan('storage:cleanup-orphans')->assertSuccessful();

        Storage::disk('object-storage')->assertExists($path);
        $this->assertNull(
            $document->fresh()->files_deleted_at,
            'a file behind a restorable case must not be stamped as swept'
        );
    }

    public function test_document_whose_case_row_is_gone_is_deleted_after_the_grace(): void
    {
        $path = 'case-files/case-was-purged.pdf';
        Storage::disk('object-storage')->put($path, '%PDF-1.4');

        $document = $this->caseDocument($path);
        $document->update(['is_deleted' => true, 'deleted_at' => now()->subDays(2)]);

        $this->purgeCaseRow();

        $this->artisan('storage:cleanup-orphans')->assertSuccessful();

        Storage::disk('object-storage')->assertMissing($path);

        // The same RESTRICT key that orphaned the row also refuses the stamp,
        // so the sweep tolerates that and moves on rather than failing the run.
        $this->assertNull($document->fresh()->files_deleted_at);
    }

    public function test_a_swept_row_is_not_swept_again_on_the_next_run(): void
    {
        $path = 'case-files/sweep-once.pdf';
        Storage::disk('object-storage')->put($path, '%PDF-1.4');

        // A live case: the document's own trash is the only thing protecting it.
        $this->caseDocument($path)->update(['is_deleted' => true, 'deleted_at' => now()->subDays(2)]);

        $this->artisan('storage:cleanup-orphans')
            ->expectsOutputToContain('Cleaned up 1 orphaned files.')
            ->assertSuccessful();
        Storage::disk('object-storage')->assertMissing($path);

        // Second nightly run: the stamp must keep the sweep off the same key.
        $this->artisan('storage:cleanup-orphans')
            ->expectsOutputToContain('Cleaned up 0 orphaned files.')
            ->assertSuccessful();
    }

    public function test_client_request_attachment_cascaded_from_a_referral_is_deleted_past_retention(): void
    {
        $request = $this->clientRequest();
        $message = ReferralClientMessage::create([
            'request_id' => $request->id,
            'body' => 'Attaching my passport scan.',
            'sender_kind' => ReferralClientMessage::SENDER_AGENCY_USER,
            'user_id' => $this->user->id,
            'kind' => ReferralClientMessage::KIND_MESSAGE,
        ]);

        $path = 'client-request-attachments/'.$request->id.'/passport.pdf';
        Storage::disk('object-storage')->put($path, '%PDF-1.4');

        $attachment = $message->attachments()->create([
            'file_name' => 'passport.pdf',
            'file_path' => $path,
            'file_type' => 'application/pdf',
            'size' => 9,
        ]);

        // Trashing the referral has to reach the attachment two levels down.
        $request->referral->delete();

        $this->assertTrue($attachment->fresh()->trashed(), 'the referral cascade should reach the attachment row');

        // The case is long past its restore window, so the object is an orphan.
        $this->backdate($attachment, 'referral_client_message_attachments', days: 2);
        $this->backdate($this->case, 'cases', days: self::RETENTION_DAYS + 5);

        $this->artisan('storage:cleanup-orphans')->assertSuccessful();

        Storage::disk('object-storage')->assertMissing($path);
        $this->assertNotNull($attachment->fresh()->files_deleted_at);
    }

    public function test_client_request_attachment_inside_the_restore_window_keeps_its_file(): void
    {
        $request = $this->clientRequest();
        $message = ReferralClientMessage::create([
            'request_id' => $request->id,
            'body' => 'Attaching my passport scan.',
            'sender_kind' => ReferralClientMessage::SENDER_AGENCY_USER,
            'user_id' => $this->user->id,
            'kind' => ReferralClientMessage::KIND_MESSAGE,
        ]);

        $path = 'client-request-attachments/'.$request->id.'/residence.pdf';
        Storage::disk('object-storage')->put($path, '%PDF-1.4');

        $attachment = $message->attachments()->create([
            'file_name' => 'residence.pdf',
            'file_path' => $path,
            'file_type' => 'application/pdf',
            'size' => 9,
        ]);

        $request->referral->delete();
        $this->backdate($attachment, 'referral_client_message_attachments', days: 2);
        $this->backdate($this->case, 'cases', days: 2);

        $this->artisan('storage:cleanup-orphans')->assertSuccessful();

        Storage::disk('object-storage')->assertExists($path);
    }

    private function caseDocument(string $path): CaseDocument
    {
        return CaseDocument::create([
            'id' => fake()->uuid(),
            'file_name' => basename($path),
            'file_path' => $path,
            'file_type' => 'application/pdf',
            'case_id' => $this->case->id,
            'user_id' => $this->user->id,
        ]);
    }

    private function clientRequest(): ReferralClientRequest
    {
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Passport renewal',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        return ReferralClientRequest::create([
            'referral_id' => $referral->id,
            'creator_user_id' => $this->user->id,
            'type' => ReferralClientRequest::TYPE_DOCUMENT_REQUEST,
            'title' => 'Upload your passport',
            'instructions' => 'Send a clear scan of the photo page.',
            'status' => ReferralClientRequest::STATUS_OPEN,
        ]);
    }

    /**
     * Move a soft-delete stamp backwards without going through the model, so
     * the cascade-written row lands outside the one-day grace period.
     */
    private function backdate(object $model, string $table, int $days): void
    {
        DB::table($table)->where('id', $model->getKey())->update([
            'is_deleted' => true,
            'deleted_at' => now()->subDays($days),
        ]);
    }

    /**
     * Reproduce what cases:purge-trashed leaves behind: the case row is gone
     * and its documents are orphaned rows that still point at a bucket key.
     *
     * case_documents.case_id is ON DELETE RESTRICT, so the reference check has
     * to stand down for the row to disappear the way a real purge makes it.
     */
    private function purgeCaseRow(): void
    {
        DB::statement('SET session_replication_role = replica');

        try {
            DB::table('cases')->where('id', $this->case->id)->delete();
        } finally {
            DB::statement('SET session_replication_role = origin');
        }
    }
}
