<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Verifies mail transport configuration by sending one real message.
 *
 * This exists because the launch check in docs/EMAIL_DELIVERY_v2.1.0.md has to
 * run from the deployed environment, not a developer laptop: egress rules and
 * injected environment variables differ. The provider dashboard is not a
 * substitute either — it says nothing about whether *this application's*
 * configuration works.
 */
class VerifyMailTransport extends Command
{
    protected $signature = 'mail:verify-transport
                            {email : Recipient address for the test message}
                            {--no-send : Report configuration only, without sending}';

    protected $description = 'Report the active mail configuration and send a test message';

    public function handle(): int
    {
        $recipient = (string) $this->argument('email');

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error("'{$recipient}' is not a valid email address.");

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');

        $this->line('');
        $this->components->twoColumnDetail('<fg=gray>Setting</>', '<fg=gray>Value</>');
        $this->components->twoColumnDetail('Mailer', $mailer);
        $this->components->twoColumnDetail('From address', (string) config('mail.from.address'));
        $this->components->twoColumnDetail('From name', (string) config('mail.from.name'));
        $this->components->twoColumnDetail('App environment', (string) config('app.env'));

        if ($mailer === 'resend') {
            $this->components->twoColumnDetail(
                'RESEND_API_KEY',
                $this->describeSecret(config('services.resend.key'))
            );
            $this->components->twoColumnDetail(
                'RESEND_WEBHOOK_SECRET',
                $this->describeSecret(config('services.resend.webhook_secret'))
            );
        }

        $this->line('');

        if (! $this->warnAboutConfiguration($mailer)) {
            return self::FAILURE;
        }

        if ($this->option('no-send')) {
            $this->components->info('Configuration reported. No message sent (--no-send).');

            return self::SUCCESS;
        }

        return $this->sendTestMessage($recipient, $mailer);
    }

    /**
     * Report configuration problems. Returns false only for a fatal one.
     */
    private function warnAboutConfiguration(string $mailer): bool
    {
        if ($mailer === 'resend' && blank(config('services.resend.key'))) {
            $this->components->error('MAIL_MAILER is "resend" but RESEND_API_KEY is empty. Every send will fail.');

            return false;
        }

        if ($mailer === 'log') {
            $this->components->warn('Mailer is "log": the message is written to the log and never delivered.');
        }

        if ($mailer === 'resend' && blank(config('services.resend.webhook_secret'))) {
            $this->components->warn(
                'RESEND_WEBHOOK_SECRET is empty. The webhook endpoint will reject all requests, '
                .'so bounces and complaints will not be recorded.'
            );
        }

        if (str_ends_with((string) config('mail.from.address'), '@example.com')) {
            $this->components->warn('MAIL_FROM_ADDRESS is still a placeholder (@example.com).');
        }

        return true;
    }

    private function sendTestMessage(string $recipient, string $mailer): int
    {
        // Captured from the event rather than a Resend API call, so what is
        // reported is the id this application actually recorded.
        $providerMessageId = null;

        Event::listen(function (MessageSent $event) use (&$providerMessageId): void {
            $header = $event->message->getHeaders()->get('X-Resend-Email-ID');
            $providerMessageId = $header?->getBodyAsString();
        });

        $sentAt = now()->toDateTimeString();

        try {
            Mail::raw(
                "Mail transport verification sent at {$sentAt} from the '".config('app.env')."' environment "
                ."using the '{$mailer}' mailer.\n\nIf you received this, outbound mail works.",
                function ($message) use ($recipient) {
                    $message->to($recipient)->subject('Mail transport verification');
                }
            );
        } catch (Throwable $e) {
            // Verbatim, not summarised: an unverified-domain rejection or a bad
            // key is only actionable if the provider's own wording survives.
            $this->components->error('Send failed. Provider response:');
            $this->line('  '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Message accepted for delivery to {$recipient}.");

        if ($providerMessageId !== null && $providerMessageId !== '') {
            $this->components->twoColumnDetail('Provider message id', $providerMessageId);
            $this->line('  Delivery events for this id will appear in email_events.');
        } elseif ($mailer === 'resend') {
            $this->components->warn(
                'No X-Resend-Email-ID header was returned, so delivery webhooks cannot be correlated.'
            );
        }

        $this->line('');
        $this->line('  Check the email_logs table for the recorded row, then confirm receipt in the inbox.');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Describe a secret without ever printing it.
     */
    private function describeSecret(mixed $value): string
    {
        if (blank($value)) {
            return '<fg=red>not set</>';
        }

        return '<fg=green>set</> ('.strlen((string) $value).' chars)';
    }
}
