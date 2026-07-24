<?php

namespace Tests\Feature;

use App\Models\GeneratedDocument;
use App\Models\User;
use App\Notifications\DownloadReady;
use App\Notifications\ReferralCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueueNotificationsExportsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Document Download Route
    // -------------------------------------------------------------------------

    #[Test]
    public function download_requires_authentication(): void
    {
        $document = GeneratedDocument::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'cases_export',
            'filename' => 'test.xlsx',
            'status' => 'pending',
        ]);

        $response = $this->get("/documents/{$document->id}/download");
        $response->assertRedirect();
    }

    #[Test]
    public function download_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $document = GeneratedDocument::create([
            'user_id' => $owner->id,
            'type' => 'cases_export',
            'filename' => 'test.xlsx',
            'status' => 'completed',
            'path' => 'generated/test.xlsx',
        ]);

        $response = $this->actingAs($otherUser)->get("/documents/{$document->id}/download");
        $response->assertStatus(403);
    }

    #[Test]
    public function download_returns_202_for_pending_document(): void
    {
        $user = User::factory()->create();

        $document = GeneratedDocument::create([
            'user_id' => $user->id,
            'type' => 'cases_export',
            'filename' => 'test.xlsx',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get("/documents/{$document->id}/download");
        $response->assertStatus(202);
        $response->assertJson(['status' => 'pending']);
    }

    #[Test]
    public function download_returns_410_for_failed_document(): void
    {
        $user = User::factory()->create();

        $document = GeneratedDocument::create([
            'user_id' => $user->id,
            'type' => 'cases_export',
            'filename' => 'test.xlsx',
            'status' => 'failed',
            'error_message' => 'Something went wrong',
        ]);

        $response = $this->actingAs($user)->get("/documents/{$document->id}/download");
        $response->assertStatus(410);
        $response->assertJson(['status' => 'failed']);
    }

    #[Test]
    public function download_returns_404_for_nonexistent_document(): void
    {
        $user = User::factory()->create();
        $fakeUuid = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($user)->get("/documents/{$fakeUuid}/download");
        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Generated Document Model
    // -------------------------------------------------------------------------

    #[Test]
    public function generated_document_has_status_helpers(): void
    {
        $user = User::factory()->create();

        $pending = GeneratedDocument::create([
            'user_id' => $user->id,
            'type' => 'cases_export',
            'filename' => 'test.xlsx',
            'status' => 'pending',
        ]);

        $completed = GeneratedDocument::create([
            'user_id' => $user->id,
            'type' => 'cases_export',
            'filename' => 'test2.xlsx',
            'status' => 'completed',
            'path' => 'generated/test2.xlsx',
        ]);

        $failed = GeneratedDocument::create([
            'user_id' => $user->id,
            'type' => 'cases_export',
            'filename' => 'test3.xlsx',
            'status' => 'failed',
            'error_message' => 'Error',
        ]);

        $this->assertTrue($pending->isPending());
        $this->assertFalse($pending->isCompleted());
        $this->assertFalse($pending->isFailed());

        $this->assertFalse($completed->isPending());
        $this->assertTrue($completed->isCompleted());
        $this->assertFalse($completed->isFailed());

        $this->assertFalse($failed->isPending());
        $this->assertFalse($failed->isCompleted());
        $this->assertTrue($failed->isFailed());
    }

    // -------------------------------------------------------------------------
    // Notification Classes Implement ShouldQueue
    // -------------------------------------------------------------------------

    #[Test]
    public function notification_classes_implement_should_queue(): void
    {
        $notifications = [
            \App\Notifications\ReferralCreated::class,
            \App\Notifications\ReferralStatusChanged::class,
            \App\Notifications\CaseStatusUpdated::class,
            \App\Notifications\CaseUpdated::class,
            \App\Notifications\MilestoneAdded::class,
            \App\Notifications\SystemAlertNotification::class,
            \App\Notifications\ReferralClientRequestActivity::class,
            \App\Notifications\DownloadReady::class,
        ];

        foreach ($notifications as $class) {
            $this->assertTrue(
                in_array(\Illuminate\Contracts\Queue\ShouldQueue::class, class_implements($class)),
                "{$class} does not implement ShouldQueue"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Export Endpoints Return Async JSON
    // -------------------------------------------------------------------------

    #[Test]
    public function cases_export_dispatches_job_and_returns_pending(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('cases.export-excel'));

        $response->assertOk();
        $response->assertJson(['status' => 'pending']);

        Queue::assertPushed(\App\Jobs\ExportDataToExcel::class, function ($job) {
            return $job->type === 'cases_export';
        });
    }

    #[Test]
    public function reports_pdf_export_dispatches_job_and_returns_pending(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('reports.export-pdf', [
            'from' => '2026-01-01',
            'to' => '2026-12-31',
        ]));

        $response->assertOk();
        $response->assertJson(['status' => 'pending']);

        Queue::assertPushed(\App\Jobs\GenerateSystemReport::class);
    }

    // -------------------------------------------------------------------------
    // DownloadReady Notification
    // -------------------------------------------------------------------------

    #[Test]
    public function download_ready_notification_has_correct_payload(): void
    {
        $user = User::factory()->create();
        $document = GeneratedDocument::create([
            'user_id' => $user->id,
            'type' => 'cases_export',
            'filename' => 'cases-export-20260724.xlsx',
            'status' => 'completed',
            'path' => 'generated/test.xlsx',
        ]);

        $notification = new DownloadReady($document);
        $data = $notification->toDatabase($user);

        $this->assertSame('download_ready', $data['type']);
        $this->assertSame($document->id, $data['generated_document_id']);
        $this->assertSame('cases-export-20260724.xlsx', $data['filename']);
        $this->assertSame('cases_export', $data['document_type']);
        $this->assertSame('ready', $data['status']);
        $this->assertStringContainsString('/documents/', $data['url']);
    }

    #[Test]
    public function download_ready_failure_notification_has_error_payload(): void
    {
        $user = User::factory()->create();
        $document = GeneratedDocument::create([
            'user_id' => $user->id,
            'type' => 'system_report_pdf',
            'filename' => 'report.pdf',
            'status' => 'failed',
            'error_message' => 'Memory limit exceeded',
        ]);

        $notification = new DownloadReady($document, failed: true);
        $data = $notification->toDatabase($user);

        $this->assertSame('download_failed', $data['type']);
        $this->assertSame('failed', $data['status']);
        $this->assertSame('Memory limit exceeded', $data['error_message']);
    }
}
