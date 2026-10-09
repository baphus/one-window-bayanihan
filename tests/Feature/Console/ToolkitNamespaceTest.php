<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Laravel\Boost\BoostServiceProvider;
use Tests\TestCase;

/**
 * Task 0.4 — Toolkit (boost) artisan namespace smoke.
 *
 * The 2026-09-14 logs recorded a Symfony NamespaceNotFoundException ×2:
 * "There are no commands defined in the boost namespace" when resolving
 * `boost:install` via direct CLI invocation. "Toolkit" in the fix plan
 * refers to this Laravel Boost dev-toolkit namespace.
 *
 * Diagnosis (2026-09-15):
 * - `Laravel\Boost\BoostServiceProvider` resolves via Composer package
 *   discovery (bootstrap/cache/packages.php) — no explicit entry in
 *   bootstrap/providers.php is required, and none was added.
 * - Local CLI `php artisan list --raw` shows the boost namespace
 *   (boost:add-skill, boost:install, boost:list-skills, boost:mcp,
 *   boost:update) both before and after `php artisan optimize:clear`.
 * - The namespace is INTENTIONALLY absent inside the test environment:
 *   `BoostServiceProvider::shouldRun()` returns false when
 *   `app()->runningUnitTests()` is true, so no `boost:*` commands are
 *   registered during tests. This is vendor-by-design (dev-only toolkit),
 *   not a misconfiguration — hence this test documents the absence
 *   instead of asserting presence (per plan step 3 alternative).
 */
class ToolkitNamespaceTest extends TestCase
{
    public function test_boost_provider_discovered(): void
    {
        $provider = $this->app->getProvider(BoostServiceProvider::class);

        $this->assertNotNull(
            $provider,
            'Expected Laravel\Boost\BoostServiceProvider to be registered via package discovery.'
        );
    }

    public function test_toolkit_namespace_intentionally_absent_during_tests(): void
    {
        $boostCommands = array_filter(
            array_keys(Artisan::all()),
            fn (string $name): bool => str_starts_with($name, 'boost:')
        );

        $this->assertEmpty(
            $boostCommands,
            'Expected no boost:* commands during tests: BoostServiceProvider::shouldRun() disables the toolkit while runningUnitTests() (vendor-by-design dev-only gating).'
        );
    }
}
