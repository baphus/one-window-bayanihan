<?php

namespace App\Console\Commands;

use App\Support\MailTransportHealth;
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
                            {email? : Recipient address for the test message; not needed with --no-send}
                            {--no-send : Report configuration only, without sending}';

    protected $description = 'Report the active mail configuration and send a test message';

    public function handle(): int
    {
        $recipient = $this->argument('email');

        // Optional rather than required so the container entrypoint can run the
        // configuration half of this check at boot. Forcing an address would
        // put a placeholder recipient into the release path purely to satisfy
        // the signature.
        if ($recipient !== null && ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error("'{$recipient}' is not a valid email address.");

            return self::FAILURE;
        }

        if ($recipient === null && ! $this->option('no-send')) {
            $this->error('A recipient address is required unless --no-send is passed.');

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

        return $this->sendTestMessage((string) $recipient, $mailer);
    }

    /**
     * Report configuration problems. Returns false only for a fatal one.
     */
    private function warnAboutConfiguration(string $mailer): bool
    {
        // Build the real transport instead of re-checking one driver's config
        // by hand.
        //
        // The outage this gate exists for was Resend::client(null) throwing a
        // TypeError inside MailManager. But the failure CLASS is "the
        // configured mailer cannot be constructed", and a hardcoded resend
        // check waves through every other member of it: MAIL_MAILER=resendd
        // (typo), a driver name that was never defined, ses without
        // credentials. Constructing it catches all of them — including drivers
        // added after this command was written.
        //
        // This is the same call path Mail::to() takes, so what passes here is
        // what will work at request time. It opens no connection: SMTP,
        // Resend and SES all defer I/O until the first send.
        // Shared with the /api/readyz probe so the boot gate and the runtime
        // gate cannot drift apart in what they consider deliverable.
        $problem = MailTransportHealth::problem($mailer);

        if ($problem !== null) {
            $this->components->error(ucfirst($problem['reason']).'. Every send will fail.');

            // Only the credential-map branch carries the runbook pointer.
            // Printing it unconditionally sent an operator staring at
            // "Mailer [resendd] is not defined" to a section about secret
            // names, which is the wrong page during a failed boot.
            if ($problem['hint'] !== null) {
                $this->line('  '.$problem['hint']);
            }

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
