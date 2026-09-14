<?php

namespace App\Console\Commands;

use App\Helpers\SecurityHelper;
use App\Models\EmailLog;
use Illuminate\Console\Command;
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

        $command = SecurityHelper::unserializeWithoutClasses($command);

        if ($command === null) {
            return null;
        }

        $commandProperties = SecurityHelper::serializedObjectProperties($command);
        $commandClass = $commandProperties['__PHP_Incomplete_Class_Name'] ?? null;

        if ($commandProperties === null || ! is_string($commandClass)) {
            return null;
        }

        if ($commandClass === 'Illuminate\\Mail\\SendQueuedMailable') {
            $mailable = $commandProperties['mailable'] ?? null;
            $mailableProperties = SecurityHelper::serializedObjectProperties($mailable);

            if ($mailableProperties === null) {
                return null;
            }

            $to = '';
            foreach (($mailableProperties['to'] ?? []) as $recipient) {
                $to = $recipient['address'] ?? $recipient[0] ?? '';
                break;
            }

            return [
                'to_email' => $to ?: '(unknown)',
                'subject' => $mailableProperties['subject'] ?? class_basename($mailableProperties['__PHP_Incomplete_Class_Name'] ?? 'Mailable'),
                'mailable_type' => $mailableProperties['__PHP_Incomplete_Class_Name'] ?? 'Illuminate\\Mail\\Mailable',
            ];
        }

        if ($commandClass === 'Illuminate\\Notifications\\SendQueuedNotifications') {
            $notificationProperties = SecurityHelper::serializedObjectProperties($commandProperties['notification'] ?? null);

            return [
                'to_email' => '(unknown)',
                'subject' => class_basename($notificationProperties['__PHP_Incomplete_Class_Name'] ?? 'Notification'),
                'mailable_type' => $notificationProperties['__PHP_Incomplete_Class_Name'] ?? 'Illuminate\\Notifications\\Notification',
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
