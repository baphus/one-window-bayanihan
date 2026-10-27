<?php

namespace App\Listeners;

use App\Helpers\SecurityHelper;
use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Schema;
use Throwable;

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

    /**
     * Whether email_logs.resent_at exists, resolved once per process.
     *
     * This listener is not allowed to be able to break mail delivery, so it
     * must not assume its own schema is deployed. A nullable column added in a
     * later migration is the one thing here that can be missing at runtime,
     * and querying it anyway throws *inside* the queued mail job — after the
     * transport has already delivered. The worker then releases the job and
     * the recipient receives the same email again on every attempt.
     *
     * The guards below turn that into a degraded feature (resend promotion
     * stops working, with a warning) instead of duplicate mail.
     */
    private ?bool $hasResentAtColumn = null;

    /**
     * Identifier of the queue job currently being processed, when there is one.
     *
     * MessageSent carries no job reference, but the payload uuid is stable
     * across retries — release() re-inserts the same payload — so it identifies
     * one send attempt exactly.
     *
     * It lives in the container rather than on this object because
     * Event::subscribe() registers the class name, so the dispatcher resolves a
     * fresh listener instance for every event; instance state set on
     * JobProcessing would be gone by the time MessageSent fires.
     */
    private const JOB_UUID_BINDING = self::class.'.current_job_uuid';

    public function handleMessageSent(MessageSent $event): void
    {
        try {
            $this->recordSent($event);
        } catch (Throwable $e) {
            // Logging must never fail the mail job: a throw here happens after
            // the message has already gone out, and the worker would treat it
            // as a failed job and send the same mail again.
            $this->warn($e, 'could not record a sent email');
        }
    }

    private function recordSent(MessageSent $event): void
    {
        $to = $this->extractToAddress($event);
        $subject = $event->message->getSubject() ?? '(no subject)';
        $mailableType = $this->extractMailableType($event);
        $providerMessageId = $this->extractProviderMessageId($event);
        $jobUuid = $this->currentJobUuid();

        // A retry that finally went out has to advance the failed row it
        // belongs to. Inserting a second 'sent' row here instead would leave
        // the original stuck on 'failed' — so the admin sees a delivered email
        // next to a resend button for it, and clicks the button.
        if ($this->promoteResentFailedLog($to, $subject, $mailableType, $providerMessageId, $jobUuid)) {
            return;
        }

        if ($this->isDuplicate($to, $subject, $mailableType, $providerMessageId, $jobUuid)) {
            return;
        }

        EmailLog::create([
            'to_email' => $to,
            'subject' => $subject,
            'mailable_type' => $mailableType,
            'status' => 'sent',
            'sent_at' => now(),
            'provider_message_id' => $providerMessageId,
            'job_uuid' => $jobUuid,
        ]);
    }

    /**
     * Remember which queue job is running, so MessageSent can identify itself.
     *
     * Worker::process() raises JobProcessing before firing the job, so by the
     * time the transport runs (and MessageSent fires) this is populated.
     */
    public function handleJobProcessing(JobProcessing $event): void
    {
        $this->rememberJobUuid($event->job->uuid());
    }

    /**
     * Clear the per-job identity so it cannot leak between jobs.
     *
     * A failing job never reaches this (the worker raises JobExceptionOccurred
     * instead), so handleJobProcessing() also overwrites the value at the
     * start of every job — this just releases it promptly on the happy path.
     */
    public function handleJobProcessed(): void
    {
        $this->rememberJobUuid(null);
    }

    /**
     * The queue payload uuid of the job being processed, if any.
     */
    private function currentJobUuid(): ?string
    {
        $uuid = app()->bound(self::JOB_UUID_BINDING) ? app(self::JOB_UUID_BINDING) : null;

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    /**
     * Store or clear the current job uuid for the life of the process.
     *
     * Scoped to the worker process rather than this listener instance, because
     * Event::subscribe() hands the dispatcher a class name and every event gets
     * a new instance.
     */
    private function rememberJobUuid(?string $uuid): void
    {
        app()->instance(self::JOB_UUID_BINDING, $uuid);
    }

    /**
     * Turn a resent 'failed' row into a delivered one, in place.
     *
     * The controller stamps resent_at when it re-queues a row, and clears it
     * again if that retry fails, so a non-null value identifies exactly the
     * rows an admin asked to retry. Matching is by job_uuid when the row has
     * one — that is the exact send attempt — falling back to recipient +
     * subject + mailable class within the resend window, which is the only
     * join available for rows written before job_uuid was populated.
     *
     * The provider message id is carried onto the promoted row so the webhook
     * endpoint keeps resolving delivery events for this message.
     *
     * @return bool Whether a failed row was advanced instead of a new one inserted.
     */
    private function promoteResentFailedLog(string $to, string $subject, string $mailableType, ?string $providerMessageId, ?string $jobUuid = null): bool
    {
        // Resending is an admin convenience; it is not worth a failed mail job
        // if this install predates the resent_at migration.
        if (! $this->hasResentAtColumn()) {
            return false;
        }

        $log = null;

        if ($jobUuid !== null) {
            $log = EmailLog::where('job_uuid', $jobUuid)
                ->where('status', 'failed')
                ->whereNotNull('resent_at')
                ->first();
        }

        $log ??= EmailLog::where('to_email', $to)
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
            'job_uuid' => $jobUuid ?? $log->job_uuid,
            'provider_message_id' => $providerMessageId ?? $log->provider_message_id,
        ])->save();

        return true;
    }

    /**
     * Guard against recording the same send twice.
     *
     * A retried queued mail is the case that matters: the transport already
     * delivered, the job was released for some later failure, and every retry
     * fires MessageSent again. job_uuid is the queue payload uuid, which
     * release() preserves, so it identifies the retries of one job exactly —
     * a time window cannot, because backoff is longer than any window short
     * enough to keep two genuine sends apart.
     *
     * The provider message id is an exact test when present. The final time
     * window only covers transports that supply neither, and is deliberately
     * short so it cannot swallow a genuine second send.
     */
    private function isDuplicate(string $to, string $subject, string $mailableType, ?string $providerMessageId, ?string $jobUuid = null): bool
    {
        if ($jobUuid !== null && EmailLog::where('job_uuid', $jobUuid)->exists()) {
            return true;
        }

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
     * Whether the email_logs table has the resent_at column.
     *
     * Cached for the life of the process; the schema does not change while a
     * worker is running.
     */
    private function hasResentAtColumn(): bool
    {
        return $this->hasResentAtColumn ??= Schema::hasColumn('email_logs', 'resent_at');
    }

    /**
     * Report a listener failure without failing the mail job that raised it.
     */
    private function warn(Throwable $e, string $context): void
    {
        logger()->warning("EmailEventSubscriber: {$context}", [
            'exception' => $e,
        ]);
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
        try {
            $this->recordFailed($event);
        } catch (Throwable $e) {
            // A failure here must not mask the original job failure.
            $this->warn($e, 'could not record a failed email');
        }
    }

    private function recordFailed(JobFailed $event): void
    {
        $payload = $event->job->payload();
        $data = $this->extractEmailData($payload);

        if ($data === null) {
            return;
        }

        // The payload uuid is the job's stable identity; release() re-inserts
        // the same payload, so a retry reports the same value. getJobId() is
        // the database row id, which is neither stable nor a uuid.
        $jobUuid = $event->job->uuid();

        // Redis job ids are not UUIDs — only store values that PostgreSQL accepts.
        $validUuid = ($jobUuid && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $jobUuid))
            ? $jobUuid : null;

        // A failed retry must land on the row it came from. queue:retry keeps
        // the payload uuid, so a retried job reports the same job_uuid as the
        // original failure; a mailable rebuilt by the resend controller gets a
        // fresh uuid instead, so fall back to the resent row it was rebuilt for.
        $log = $this->findFailedLog($data, $validUuid);

        if ($log !== null) {
            $attributes = [
                'job_uuid' => $validUuid ?? $log->job_uuid,
                'error_message' => $event->exception->getMessage(),
            ];

            // Reopens the row for another attempt without a second row.
            if ($this->hasResentAtColumn()) {
                $attributes['resent_at'] = null;
            }

            $log->forceFill($attributes)->save();

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

        if (! $this->hasResentAtColumn()) {
            return null;
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
            JobProcessing::class => 'handleJobProcessing',
            JobProcessed::class => 'handleJobProcessed',
        ];
    }
}
