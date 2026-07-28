<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Can the configured mailer actually deliver?
 *
 * One definition, used by both gates that ask the question: the container
 * entrypoint preflight (mail:verify-transport) and the /api/readyz probe. They
 * previously could not disagree because only one of them existed; keeping the
 * rule here means they still cannot.
 *
 * Written after a release shipped the Resend API key under a variable name the
 * application did not read. config('services.resend.key') was null,
 * Resend::client(null) threw a TypeError, and because Mail::to() resolves the
 * transport eagerly, every intake OTP, MFA challenge and password reset
 * returned 500 — while /up and /api/readyz both reported the release healthy.
 */
class MailTransportHealth
{
    /**
     * Drivers whose credential is silently accepted when blank.
     *
     * Only drivers actually in use are listed. Guessing config paths for
     * drivers this deployment has never configured would produce false
     * failures, which is worse than the gap.
     *
     * @var array<string, array{0: string, 1: string}> driver => [config path, env var name]
     */
    public const REQUIRED_CREDENTIALS = [
        'resend' => ['services.resend.key', 'RESEND_API_KEY'],
    ];

    /**
     * Why the configured mailer cannot deliver, or null when it can.
     *
     * A pure predicate: it opens no connection and reports nothing. SMTP,
     * Resend and SES all defer I/O to the first send, so this is cheap enough
     * for a readiness probe polled every 30 seconds and for container boot.
     *
     * Reporting is deliberately left to the CALLER. An earlier revision called
     * report() here, which (a) contradicted the no-connection promise above,
     * since the Sentry reporter in bootstrap/app.php makes an outbound HTTP
     * call, and (b) had no dedupe — a failed resolve is never cached by
     * MailManager, so a monitor polling a broken mailer would re-report a full
     * stack trace every poll, flooding the very log you are reading to
     * diagnose it. ReadinessController::checkDatabase() already reports at the
     * call site; this matches that convention.
     *
     * @return array{reason: string, hint: ?string}|null
     */
    public static function problem(?string $mailer = null): ?array
    {
        $mailer ??= (string) config('mail.default');

        // Build the real transport rather than re-checking one driver's config
        // by hand. The failure class is "the configured mailer cannot be
        // constructed"; a hardcoded Resend-key check waves through
        // MAIL_MAILER=resendd, a driver that was never defined, and any driver
        // added after this code was written. This is the same path Mail::to()
        // takes, so what passes here is what works at request time.
        try {
            Mail::mailer($mailer)->getSymfonyTransport();
        } catch (Throwable $e) {
            return [
                'reason' => "the '{$mailer}' mailer cannot be constructed: ".$e->getMessage(),
                'hint' => null,
            ];
        }

        // Necessary but not sufficient. An ABSENT variable arrives as null and
        // Resend::client(null) throws — caught above. A variable that is
        // PRESENT BUT EMPTY arrives as '', satisfies the string parameter,
        // builds a client happily, and then 401s on every real send. Resend
        // validates nothing: Resend::client() hands straight to ApiKey::from(),
        // which is a bare constructor. Both spellings of "no key" have to fail.
        foreach (self::REQUIRED_CREDENTIALS as $driver => [$configKey, $envName]) {
            if ($mailer === $driver && blank(config($configKey))) {
                return [
                    'reason' => "MAIL_MAILER is \"{$driver}\" but {$envName} is empty",
                    'hint' => 'A key set under a different variable name reads back as empty — '
                        .'see docs/DEPLOYMENT_PRODUCTION_AWS_v1.6.0.md section 0.',
                ];
            }
        }

        return null;
    }
}
