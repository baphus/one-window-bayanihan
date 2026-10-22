<?php

namespace App\Listeners;

use App\Helpers\SecurityHelper;
use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;

class EmailEventSubscriber
{
    /**
     * How far back a resent row stays eligible for promotion on delivery.
     *
     * A retry can sit on the queue behind a backlog before the transport runs,
     * so the window is generous; it is bounded because resent_at is the only
     * thing separating "an admin asked for this mail again" from a fresh send.
     */
    private const RESEND_MATCH_WINDOW_HOURS = 24;

    public function handleMessageSent(MessageSent $event): void
    {
        $to = $this->extractToAddress($event);
        $subject = $event->message->getSubject() ?? '(no subject)';
        $mailableType = $this->extractMailableType($event);
        $providerMessageId = $this->extractProviderMessageId($event);

        // A retry that finally went out has to advance the failed row it
        // belongs to. Inserting a second 'sent' row here instead would leave
        // the original stuck on 'failed' — so the admin sees a delivered email
        // next to a resend button for it, and clicks the button.
        if ($this->promoteResentFailedLog($to, $subject, $mailableType, $providerMessageId)) {
            return;
        }

        if ($this->isDuplicate($to, $subject, $mailableType, $providerMessageId)) {
            return;
        }

        EmailLog::create([
            'to_email' => $to,
            'subject' => $subject,
            'mailable_type' => $mailableType,
            'status' => 'sent',
            'sent_at' => now(),
            'provider_message_id' => $providerMessageId,
        ]);
    }

    /**
     * Turn a resent 'failed' row into a delivered one, in place.
     *
     * The controller stamps resent_at when it re-queues a row, and clears it
     * again if that retry fails, so a non-null value identifies exactly the
     * rows an admin asked to retry. MessageSent carries no job reference, so
     * recipient + subject + mailable class within that window is the join back
     * to the original failure; the most recently resent row wins when several
     * match.
     *
     * The provider message id is carried onto the promoted row so the webhook
     * endpoint keeps resolving delivery events for this message.
     *
     * @return bool Whether a failed row was advanced instead of a new one inserted.
     */
    private function promoteResentFailedLog(string $to, string $subject, string $mailableType, ?string $providerMessageId): bool
    {
        $log = EmailLog::where('to_email', $to)
            ->where('subject', $subject)
            ->where('mailable_type', $mailableType)
            ->where('status', 'failed')
            ->whereNotNull('resent_at')
            ->where('resent_at', '>=', now()->subHours(self::RESEND_MATCH_WINDOW_HOURS))
            ->orderByDesc('resent_at')
            ->first();

        if ($log === null) {
            return false;
        }

        $log->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'error_message' => null,
            'provider_message_id' => $providerMessageId ?? $log->provider_message_id,
        ])->save();

        return true;
    }

    /**
     * Guard against a double-fired MessageSent creating two rows.
     *
     * When the provider supplied a message id, that is an exact test. Otherwise
     * fall back to a short time window — imprecise in both directions, but the
     * only signal available for the log and smtp transports. sent_at is checked
     * alongside created_at because a row promoted from 'failed' keeps its
     * original creation time while its send time is now.
     */
    private function isDuplicate(string $to, string $subject, string $mailableType, ?string $providerMessageId): bool
    {
        if ($providerMessageId !== null) {
            return EmailLog::where('provider_message_id', $providerMessageId)->exists();
        }

        return EmailLog::where('to_email', $to)
            ->where('subject', $subject)
            ->where('mailable_type', $mailableType)
            ->where('status', 'sent')
            ->where(function ($query) {
                $query->where('created_at', '>=', now()->subSeconds(2))
                    ->orWhere('sent_at', '>=', now()->subSeconds(2));
            })
            ->exists();
    }

    /**
     * Read the provider's message id from the sent message.
     *
     * Illuminate\Mail\Transport\ResendTransport stamps X-Resend-Email-ID onto
     * the message after a successful API call, and Resend's webhooks carry the
     * same value at data.email_id — so this is the join between an outbound
     * send and its later delivery events.
     *
     * Absent for every other transport, so null is expected, not an error.
     */
    private function extractProviderMessageId(MessageSent $event): ?string
    {
        $header = $event->message->getHeaders()->get('X-Resend-Email-ID');

        if ($header === null) {
            return null;
        }

        $value = trim($header->getBodyAsString());

        return $value === '' ? null : $value;
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $payload = $event->job->payload();
        $data = $this->extractEmailData($payload);

        if ($data === null) {
            return;
        }

        $jobUuid = $event->job->getJobId();

        // Redis job IDs are not UUIDs — only store values that PostgreSQL accepts.
        $validUuid = ($jobUuid && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $jobUuid))
            ? $jobUuid : null;

        // A failed retry must land on the row it came from. queue:retry keeps
        // the payload uuid, so a retried job reports the same job_uuid as the
        // original failure; a mailable rebuilt by the resend controller gets a
        // fresh uuid instead, so fall back to the resent row it was rebuilt for.
        $log = $this->findFailedLog($data, $validUuid);

        if ($log !== null) {
            $log->forceFill([
                'job_uuid' => $validUuid ?? $log->job_uuid,
                'error_message' => $event->exception->getMessage(),
                // Reopens the row for another attempt without a second row.
                'resent_at' => null,
            ])->save();

            return;
        }

        EmailLog::create([
            'to_email' => $data['to_email'],
            'subject' => $data['subject'],
            'mailable_type' => $data['mailable_type'],
            'status' => 'failed',
            'job_uuid' => $validUuid,
            'error_message' => $event->exception->getMessage(),
        ]);
    }

    /**
     * Locate the failed row a re-failed job belongs to.
     *
     * @param  array<string, string>  $data
     */
    private function findFailedLog(array $data, ?string $jobUuid): ?EmailLog
    {
        if ($jobUuid !== null) {
            $log = EmailLog::where('job_uuid', $jobUuid)->first();

            if ($log !== null) {
                return $log;
            }
        }

        return EmailLog::where('to_email', $data['to_email'])
            ->where('subject', $data['subject'])
            ->where('mailable_type', $data['mailable_type'])
            ->where('status', 'failed')
            ->whereNotNull('resent_at')
            ->where('resent_at', '>=', now()->subHours(self::RESEND_MATCH_WINDOW_HOURS))
            ->orderByDesc('resent_at')
            ->first();
    }

    /**
     * Extract the primary recipient email from a MessageSent event.
     */
    private function extractToAddress(MessageSent $event): string
    {
        $to = $event->message->getTo();

        if (! empty($to)) {
            $addresses = array_values($to);

            return $addresses[0]->getAddress();
        }

        return '(unknown)';
    }

    /**
     * Extract the mailable class name from a MessageSent event.
     */
    private function extractMailableType(MessageSent $event): string
    {
        if (isset($event->data['__laravel_mailable'])) {
            $mailable = $event->data['__laravel_mailable'];

            return is_string($mailable) ? $mailable : get_class($mailable);
        }

        if (isset($event->data['__laravel_notification'])) {
            $notification = $event->data['__laravel_notification'];

            return is_string($notification) ? $notification : get_class($notification);
        }

        return 'Illuminate\Mail\Mailable';
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

        // Handle SendQueuedMailable
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

        // Handle queued notifications
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
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            MessageSent::class => 'handleMessageSent',
            JobFailed::class => 'handleJobFailed',
        ];
    }
}
