<?php

namespace App\Console\Commands;

use App\Helpers\SecurityHelper;
use App\Models\EmailLog;
use Illuminate\Console\Command;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;

class SyncFailedEmails extends Command
{
    protected $signature = 'emails:sync-failed';

    protected $description = 'Import existing failed mail jobs from failed_jobs table into email_logs';

    public function handle(): int
    {
        $synced = 0;
        $failedJobs = $this->getFailedMailJobs();

        foreach ($failedJobs as $job) {
            if (EmailLog::where('job_uuid', $job->uuid)->exists()) {
                continue;
            }

            $payload = json_decode($job->payload, true);
            $data = $this->extractEmailData($payload);

            if ($data === null) {
                continue;
            }

            EmailLog::create([
                'to_email' => $data['to_email'],
                'subject' => $data['subject'],
                'mailable_type' => $data['mailable_type'],
                'status' => 'failed',
                'job_uuid' => $job->uuid,
                'error_message' => $this->truncateException($job->exception),
            ]);

            $synced++;
        }

        $this->info("Synced {$synced} failed mail entries into email_logs.");

        return 0;
    }

    /**
     * Get failed jobs that contain mail-related payloads.
     *
     * @return array<int, object{uuid: string, payload: string, exception: string}>
     */
    private function getFailedMailJobs(): array
    {
        $results = DB::table('failed_jobs')
            ->where('payload', 'like', '%SendQueuedMailable%')
            ->orWhere('payload', 'like', '%SendQueuedNotifications%')
            ->get(['uuid', 'payload', 'exception']);

        return $results->all();
    }

    /**
     * Parse a failed job payload to extract email metadata.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>|null
     */
    private function extractEmailData(array $payload): ?array
    {
        $command = $payload['data']['command'] ?? null;

        if ($command === null) {
            return null;
        }

        $command = SecurityHelper::safeUnserialize($command, [
            SendQueuedMailable::class,
            SendQueuedNotifications::class,
        ]);

        if ($command === null) {
            return null;
        }

        if ($command instanceof SendQueuedMailable) {
            $mailable = $command->mailable;

            $to = '';
            foreach ($mailable->to as $recipient) {
                $to = $recipient['address'] ?? $recipient[0] ?? '';
                break;
            }

            return [
                'to_email' => $to ?: '(unknown)',
                'subject' => $mailable->subject ?? class_basename($mailable),
                'mailable_type' => get_class($mailable),
            ];
        }

        if ($command instanceof SendQueuedNotifications) {
            $notification = $command->notification;
            $notifiables = $command->notifiables;

            $to = '';
            if (! empty($notifiables)) {
                $notifiable = is_array($notifiables) ? $notifiables[0] : $notifiables;
                if (method_exists($notifiable, 'routeNotificationFor')) {
                    $to = $notifiable->routeNotificationFor('mail', $notification) ?? '';
                }
            }

            return [
                'to_email' => is_string($to) ? $to : (is_array($to) ? ($to[0] ?? '(unknown)') : '(unknown)'),
                'subject' => class_basename($notification),
                'mailable_type' => get_class($notification),
            ];
        }

        return null;
    }

    /**
     * Truncate exception messages to a reasonable length.
     */
    private function truncateException(string $exception): string
    {
        $lines = explode("\n", $exception);

        return $lines[0] ?? $exception;
    }
}
