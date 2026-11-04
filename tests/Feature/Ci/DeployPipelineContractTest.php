<?php

namespace Tests\Feature\Ci;

use Tests\TestCase;

/**
 * The deploy workflows are the release path, and nothing else executes them
 * before production. Each guarantee below was missing or wrong at some point,
 * and each one fails silently when deleted: a release still goes green, it just
 * goes green in a way that cannot be recovered from.
 */
class DeployPipelineContractTest extends TestCase
{
    private function deploy(): string
    {
        return (string) file_get_contents(base_path('.github/workflows/deploy.yml'));
    }

    /**
     * A tag typed into the dispatch form used to reach shell commands and the
     * deployment payload unchecked, so anyone with Actions write access could
     * promote an image built from an unreviewed branch.
     */
    public function test_the_image_tag_is_validated_as_a_sha_on_main(): void
    {
        $workflow = $this->deploy();

        $this->assertMatchesRegularExpression(
            '/\^\[0-9a-f\]\{40\}\$/',
            $workflow,
            'deploy.yml must reject anything that is not a full commit SHA'
        );

        $this->assertStringContainsString(
            'compare/$IMAGE_TAG...main',
            $workflow,
            'deploy.yml must prove the tag is an ancestor of main before deploying it'
        );
    }

    /**
     * The rows in the snapshot exist to restore from. Creating one is
     * asynchronous, so without waiting the container can migrate while the
     * snapshot is still being taken.
     */
    public function test_the_pre_deploy_snapshot_is_waited_for(): void
    {
        $workflow = $this->deploy();

        $this->assertStringContainsString('relationalDatabaseSnapshot.state', $workflow);
        $this->assertMatchesRegularExpression(
            '/STATE" = "available"|STATE" != "available"/',
            $workflow,
            'The snapshot must be polled until it is available before the deployment is submitted'
        );
    }

    /**
     * A container that boots but is broken never trips the platform health
     * check, so the platform has no reason to restore the previous deployment.
     * The pipeline has to do it.
     */
    public function test_a_failed_gate_rolls_the_previous_image_back(): void
    {
        $workflow = $this->deploy();

        $this->assertStringContainsString('id: previous', $workflow, 'The image serving before the deploy must be recorded');
        $this->assertStringContainsString('deployment-activated', $workflow, 'Rollback must only run once the new image actually served');

        $this->assertMatchesRegularExpression(
            '/name: Roll back to the previous deployment\s*\n\s+if: failure\(\)/',
            $workflow,
            'A failed health gate must redeploy the previous image instead of leaving the broken release live'
        );

        $this->assertStringContainsString(
            "'.containers.app.image = \$image'",
            $workflow,
            'The rollback must reuse the rendered payload with the previous image'
        );
    }

    /**
     * ECR scans after the push, so the build is the only place a new image can
     * be stopped before someone promotes it.
     */
    public function test_the_build_gate_fails_on_critical_findings(): void
    {
        $build = (string) file_get_contents(base_path('.github/workflows/build-image.yml'));

        $this->assertStringContainsString('findingSeverityCounts', $build);
        $this->assertMatchesRegularExpression(
            '/if \[ "\$CRITICAL" -gt 0 \]; then\s*\n\s+echo "::error::/',
            $build,
            'build-image.yml must fail the build on CRITICAL findings rather than only reporting them'
        );
    }

    /**
     * Backlog counters describe data that predates the release. Gating on them
     * let a pre-existing backlog block every subsequent fix.
     */
    public function test_the_readiness_gate_ignores_preexisting_backlog(): void
    {
        $this->assertStringContainsString(
            'queue_backlog|failed_jobs',
            $this->deploy(),
            'The release gate must not fail on backlog counters that predate the deployment'
        );
    }
}
