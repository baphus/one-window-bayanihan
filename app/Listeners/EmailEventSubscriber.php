<?php

namespace App\Listeners;

use App\Helpers\SecurityHelper;
use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;

class EmailEventSubscriber
{
    public function handleMessageSent(MessageSent $event): void
    {
        $to = $this->extractToAddress($event);
        $subject = $event->message->getSubject() ?? '(no subject)';
        $mailableType = $this->extractMailableType($event);
        $providerMessageId = $this->extractProviderMessageId($event);

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
     * Guard against a double-fired MessageSent creating two rows.
     *
     * When the provider supplied a message id, that is an exact test. Otherwise
     * fall back to a short time window — imprecise in both directions, but the
     * only signal available for the log and smtp transports.
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
            ->where('created_at', '>=', now()->subSeconds(2))
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
