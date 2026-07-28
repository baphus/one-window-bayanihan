<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A release must not be declared healthy while outbound mail is dead.
 *
 * /up never touches mail and /api/readyz did not either, so the deployment that
 * broke every OTP, MFA challenge and password reset passed both gates and went
 * live. mail:verify-transport already knew how to detect the fault; nothing ran
 * it. These tests pin both halves: the command's exit codes, and the fact that
 * the container entrypoint actually invokes it before serving traffic.
 */
class MailTransportPreflightTest extends TestCase
{
    #[Test]
    public function test_preflight_fails_when_resend_is_selected_without_a_key(): void
    {
        config([
            'mail.default' => 'resend',
            'services.resend.key' => null,
        ]);

        $this->artisan('mail:verify-transport --no-send')->assertExitCode(1);
    }

    #[Test]
    public function test_preflight_fails_when_the_resend_key_is_an_empty_string(): void
    {
        // env() returns '' rather than null for a variable that is present but
        // unset, which is what a secret wired to a missing GitHub secret gives.
        config([
            'mail.default' => 'resend',
            'services.resend.key' => '',
        ]);

        $this->artisan('mail:verify-transport --no-send')->assertExitCode(1);
    }

    #[Test]
    public function test_preflight_passes_when_resend_has_a_key(): void
    {
        config([
            'mail.default' => 'resend',
            'services.resend.key' => 're_test_key_value',
        ]);

        $this->artisan('mail:verify-transport --no-send')->assertExitCode(0);
    }

    #[Test]
    public function test_preflight_passes_for_the_log_mailer(): void
    {
        config(['mail.default' => 'log']);

        $this->artisan('mail:verify-transport --no-send')->assertExitCode(0);
    }

    // The gate must catch the CLASS of fault — "the configured mailer cannot be
    // constructed" — not just the one instance of it that caused the outage.
    // A hardcoded resend-key check waves all of these through.

    #[Test]
    public function test_preflight_fails_on_a_misspelled_mailer_name(): void
    {
        config(['mail.default' => 'resendd']);

        $this->artisan('mail:verify-transport --no-send')->assertExitCode(1);
    }

    #[Test]
    public function test_preflight_fails_on_a_mailer_that_is_not_defined(): void
    {
        config(['mail.default' => 'postmark-but-not-configured']);

        $this->artisan('mail:verify-transport --no-send')->assertExitCode(1);
    }

    #[Test]
    public function test_preflight_needs_no_recipient_when_it_is_not_sending(): void
    {
        // The entrypoint has no address to offer. Requiring one would force a
        // placeholder into the boot path purely to satisfy the signature.
        config(['mail.default' => 'log']);

        $this->artisan('mail:verify-transport', ['--no-send' => true])->assertExitCode(0);
    }

    #[Test]
    public function test_preflight_still_validates_a_recipient_it_is_given(): void
    {
        config(['mail.default' => 'log']);

        $this->artisan('mail:verify-transport', ['email' => 'not-an-address', '--no-send' => true])
            ->assertExitCode(1);
    }

    #[Test]
    public function test_entrypoint_runs_the_preflight_before_serving_traffic(): void
    {
        $entrypoint = (string) file_get_contents(base_path('docker/php/docker-entrypoint.sh'));

        $this->assertStringContainsString(
            'mail:verify-transport --no-send',
            $entrypoint,
            'The container must fail its own release when the mailer is misconfigured. '
            .'Both /up and /api/readyz pass while outbound mail is completely dead.'
        );
    }

    #[Test]
    public function test_entrypoint_preflight_fails_the_boot_rather_than_warning(): void
    {
        $entrypoint = (string) file_get_contents(base_path('docker/php/docker-entrypoint.sh'));

        // The block must be `if ! php artisan … ; then … exit 1; fi`. A bare
        // invocation, or one ending in `|| true`, turns the gate into a log
        // line — which is exactly how a previous defect in this file let a
        // failed route:cache serve traffic.
        $this->assertMatchesRegularExpression(
            '/if\s*!\s*php artisan mail:verify-transport[^\n]*\n(?:[^\n]*\n){0,4}?\s*exit 1/',
            $entrypoint,
            'The mail preflight does not exit non-zero on failure, so it cannot fail a release.'
        );
    }

    #[Test]
    public function test_entrypoint_runs_the_preflight_before_migrations(): void
    {
        $entrypoint = (string) file_get_contents(base_path('docker/php/docker-entrypoint.sh'));

        $preflight = $this->preflightInvocationOffset($entrypoint);
        $migrate = strpos($entrypoint, 'artisan migrate --force');

        $this->assertIsInt($migrate);

        // A container that is going to refuse to start must not mutate the
        // schema on its way out.
        $this->assertLessThan(
            $migrate,
            $preflight,
            'The mail preflight runs after migrations, so a container destined to refuse '
            .'startup applies schema changes first.'
        );
    }

    #[Test]
    public function test_entrypoint_preflight_is_skipped_outside_deployed_environments(): void
    {
        $entrypoint = (string) file_get_contents(base_path('docker/php/docker-entrypoint.sh'));

        $preflight = $this->preflightInvocationOffset($entrypoint);
        $guard = strrpos(substr($entrypoint, 0, $preflight), '"${APP_ENV}" != "local"');

        $this->assertIsInt(
            $guard,
            'The mail preflight is not inside an APP_ENV guard, so it would also gate local runs.'
        );

        // Nearest-preceding is not enough on its own: the config:cache block
        // above has its own identical guard, so a search that merely finds
        // "some guard earlier in the file" passes even after the mail block's
        // own guard is deleted. Require it to be immediately above the gate.
        $between = substr($entrypoint, $guard, $preflight - $guard);

        $this->assertLessThanOrEqual(
            3,
            substr_count($between, "\n"),
            'The nearest APP_ENV guard before the mail preflight belongs to another block, '
            .'so the preflight is not actually guarded.'
        );
    }

    /**
     * Offset of the preflight INVOCATION, not the first mention of its name.
     *
     * The entrypoint discusses `mail:verify-transport` in a comment above the
     * gate. Anchoring on the bare command name resolved to that prose, which
     * made the ordering and guard assertions pass even after the gate itself
     * was moved below migrations or stripped of its guard — the precise
     * regressions they exist to prevent.
     */
    private function preflightInvocationOffset(string $entrypoint): int
    {
        $offset = strpos($entrypoint, 'if ! php artisan mail:verify-transport');

        $this->assertIsInt(
            $offset,
            'No `if ! php artisan mail:verify-transport` invocation found in the entrypoint.'
        );

        return $offset;
    }
}
