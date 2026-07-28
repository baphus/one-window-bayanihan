<?php

namespace Tests\Feature;

use App\Support\MailTransportHealth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The deploy pipeline and the application must agree on the NAME of the Resend
 * API key environment variable.
 *
 * They did not. config/services.php read RESEND_API_KEY while
 * .github/workflows/deploy.yml shipped the secret to the container as
 * RESEND_KEY. config('services.resend.key') was therefore null in production,
 * MailManager::createResendTransport() called Resend::client(null), and because
 * Mail::to() resolves the transport eagerly EVERY outbound mail attempt —
 * intake OTP, MFA challenge, password reset — threw a TypeError and returned a
 * 500 before anything was queued.
 *
 * No functional test could have caught it: the rest of the suite calls
 * Mail::fake(), which swaps the MailManager out entirely so the real transport
 * is never constructed. The contract has to be asserted against the deployment
 * artefacts themselves, which is what this test does.
 */
class DeploymentMailEnvContractTest extends TestCase
{
    #[Test]
    public function test_deploy_artifacts_supply_the_resend_env_var_the_app_reads(): void
    {
        $expected = $this->resendKeyEnvName();

        foreach ($this->deploymentArtifacts() as $label => $path) {
            $this->assertStringContainsString(
                $expected,
                (string) file_get_contents($path),
                "{$label} never sets {$expected}, the variable config/services.php reads. "
                .'Resend::client() receives null and every send returns 500.'
            );
        }
    }

    #[Test]
    public function test_deploy_artifacts_reference_no_other_resend_key_name(): void
    {
        $expected = $this->resendKeyEnvName();

        foreach ($this->deploymentArtifacts() as $label => $path) {
            preg_match_all('/RESEND_[A-Z0-9_]*KEY/', (string) file_get_contents($path), $matches);

            foreach (array_unique($matches[0]) as $found) {
                $this->assertSame(
                    $expected,
                    $found,
                    "{$label} references {$found}, but the application reads {$expected}. "
                    .'A key shipped under the wrong name is indistinguishable from no key at all.'
                );
            }
        }
    }

    #[Test]
    public function test_the_pipeline_guard_covers_every_credential_the_app_requires(): void
    {
        // deploy.yml cannot call MailTransportHealth — the deploy runner has no
        // PHP toolchain, by design. So the guard necessarily re-states the
        // driver/credential pairs in shell, which is a second source of truth.
        // This test is what keeps it in step: add an entry to
        // REQUIRED_CREDENTIALS without extending the guard and the empty-secret
        // case for that driver would only be caught AFTER the deployment is
        // submitted, which is the late failure the guard exists to prevent.
        $workflow = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));

        foreach (MailTransportHealth::REQUIRED_CREDENTIALS as $driver => [, $envName]) {
            $this->assertStringContainsString(
                "\"{$driver}\"",
                $workflow,
                "deploy.yml's mail guard does not test for the '{$driver}' mailer, "
                .'which MailTransportHealth treats as requiring a credential.'
            );

            $this->assertStringContainsString(
                $envName,
                $workflow,
                "deploy.yml never reads {$envName}, so it cannot detect that the "
                ."'{$driver}' credential is empty before deploying."
            );
        }
    }

    /**
     * The env var name config/services.php actually reads for the Resend key.
     *
     * Derived rather than hardcoded so that renaming the variable in config
     * cannot leave this test asserting a name nothing uses any more.
     */
    private function resendKeyEnvName(): string
    {
        $services = (string) file_get_contents(base_path('config/services.php'));

        $matched = preg_match(
            "/'resend'\s*=>\s*\[.*?'key'\s*=>\s*env\(\s*'([A-Z0-9_]+)'/s",
            $services,
            $m
        );

        $this->assertSame(
            1,
            $matched,
            'Could not determine the Resend key env var from config/services.php.'
        );

        return $m[1];
    }

    /**
     * @return array<string, string>
     */
    private function deploymentArtifacts(): array
    {
        return [
            '.github/workflows/deploy.yml' => base_path('.github/workflows/deploy.yml'),
            'deploy/lightsail/app-deployment.template.json' => base_path('deploy/lightsail/app-deployment.template.json'),
        ];
    }
}
