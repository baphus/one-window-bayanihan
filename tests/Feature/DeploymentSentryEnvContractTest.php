<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Keep the Sentry variable read by Laravel aligned with the value injected into
 * the Lightsail container by the reusable deployment workflow.
 */
class DeploymentSentryEnvContractTest extends TestCase
{
    #[Test]
    public function test_deploy_workflow_accepts_the_sentry_dsn_as_an_environment_variable(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));

        $this->assertStringContainsString(
            'SENTRY_LARAVEL_DSN: ${{ secrets.SENTRY_LARAVEL_DSN || vars.SENTRY_LARAVEL_DSN }}',
            $workflow,
            'The deploy workflow must pass a production Environment variable to Lightsail, '
            .'while preferring an Environment secret when both are configured.'
        );
    }

    #[Test]
    public function test_lightsail_payload_uses_the_sentry_env_name_read_by_laravel(): void
    {
        $config = (string) file_get_contents(base_path('config/sentry.php'));
        $workflow = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));

        $matched = preg_match("/'dsn'\s*=>\s*env\(\s*'([A-Z0-9_]+)'/", $config, $matches);

        $this->assertSame(1, $matched, 'Could not determine the Sentry DSN env name from config/sentry.php.');
        $this->assertStringContainsString(
            $matches[1].': $sentry',
            $workflow,
            "The Lightsail payload does not set {$matches[1]}, the variable config/sentry.php reads."
        );
    }
}
