<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobFailed;

class EmailEventSubscriber
{
    public function handleMessageSent(MessageSent $event): void
    {
        $to = $this->extractToAddress($event);
        $subject = $event->message->getSubject() ?? '(no subject)';
        $mailableType = $this->extractMailableType($event);

        EmailLog::create([
            'to_email' => $to,
            'subject' => $subject,
            'mailable_type' => $mailableType,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $payload = $event->job->payload();
        $data = $this->extractEmailData($payload);

        if ($data === null) {
            return;
        }

        EmailLog::create([
            'to_email' => $data['to_email'],
            'subject' => $data['subject'],
            'mailable_type' => $data['mailable_type'],
            'status' => 'failed',
            'job_uuid' => $event->job->getJobId(),
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

        $command = unserialize($command);

        // Handle SendQueuedMailable
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

        // Handle queued notifications
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
                'subject' => method_exists($notification, 'toMail')
                    ? class_basename($notification)
                    : get_class($notification),
                'mailable_type' => get_class($notification),
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
