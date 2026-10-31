<?php

namespace Tests\Feature\Ci;

use Tests\TestCase;

/**
 * `VITE_APP_ENV` is the only value that tells the browser bundle which Sentry
 * environment its errors belong to. It used to be inferred from the ref
 * (`github.ref_type == 'tag' && 'production' || 'staging'`), so a manual build
 * dispatched from a branch always produced a staging bundle — a production
 * release could only be built by cutting a tag, and nothing in the run said so.
 *
 * These tests pin the replacement: the operator states the environment at
 * dispatch time and the build arg reads that input verbatim.
 */
class BuildEnvironmentTest extends TestCase
{
    private function workflow(): string
    {
        return file_get_contents(base_path('.github/workflows/build-image.yml'));
    }

    public function test_dispatch_offers_the_supported_frontend_environments(): void
    {
        $workflow = $this->workflow();

        $this->assertMatchesRegularExpression(
            '/workflow_dispatch:\s*\n\s+inputs:/',
            $workflow,
            'build-image.yml must declare dispatch inputs'
        );

        $this->assertMatchesRegularExpression(
            '/vite_app_env:\s*\n(?:\s+#.*\n)*\s+description:.*\n(?:\s+#.*\n)*\s+type:\s*choice/m',
            $workflow,
            'vite_app_env must be a choice input'
        );

        preg_match('/vite_app_env:.*?(?=\n {6}\w|\n\w|\z)/s', $workflow, $block);
        $this->assertNotEmpty($block, 'vite_app_env input block found');

        foreach (['staging', 'production'] as $environment) {
            $this->assertMatchesRegularExpression(
                '/^\s+- '.$environment.'$/m',
                $block[0],
                "The choice input must offer {$environment}"
            );
        }

        // Defaulting to the non-production value keeps the branch-build case
        // (the one that used to be silently staging anyway) explicit and safe.
        $this->assertMatchesRegularExpression('/^\s+default:\s*staging$/m', $block[0]);
    }

    public function test_build_receives_the_selected_environment_verbatim(): void
    {
        $workflow = $this->workflow();

        $this->assertMatchesRegularExpression(
            '/VITE_APP_ENV=\$\{\{\s*inputs\.vite_app_env\s*\}\}/',
            $workflow,
            'The build arg must pass the dispatch input through unchanged'
        );
    }

    public function test_environment_is_not_inferred_from_the_ref(): void
    {
        $this->assertStringNotContainsString(
            "github.ref_type == 'tag'",
            $this->workflow(),
            'The frontend environment must not be derived from the branch or tag being built'
        );

        $this->assertStringNotContainsString(
            'github.ref_name',
            $this->workflow(),
            'The frontend environment must not be derived from the ref name'
        );
    }

    public function test_php_base_image_version_is_declared_in_global_scope(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $globalScope = preg_split('/^FROM\s/im', $dockerfile, 2)[0];

        $this->assertMatchesRegularExpression('/^ARG PHP_VERSION=8\.4\.\d+$/m', $globalScope);
        $this->assertStringContainsString('FROM php:${PHP_VERSION}-fpm AS app', $dockerfile);
    }

    public function test_dockerfile_default_matches_the_dispatch_default(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertMatchesRegularExpression(
            '/ARG VITE_APP_ENV="staging"/',
            $dockerfile,
            'A bare docker build must not label a bundle "production" by default'
        );
    }
}
