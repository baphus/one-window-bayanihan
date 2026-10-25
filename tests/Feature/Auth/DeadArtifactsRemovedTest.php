<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The public self-registration controller could mint a CASE_MANAGER account and
 * the root `cleanup-create*.cjs` files were one-off codemods against
 * Case/Create.jsx. Both were unrouted/unconsumed; these assertions keep dead
 * privileged code from quietly reappearing as a "history" artifact.
 */
class DeadArtifactsRemovedTest extends TestCase
{
    public function test_the_unrouted_privileged_registration_controller_is_gone(): void
    {
        $this->assertFileDoesNotExist(app_path('Http/Controllers/Auth/RegisteredUserController.php'));
    }

    public function test_no_route_points_at_the_privileged_registration_controller(): void
    {
        $offenders = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains(
                (string) $route->getActionName(),
                'RegisteredUserController'
            ))
            ->map(fn ($route) => $route->uri())
            ->all();

        $this->assertSame([], $offenders, 'No route may resolve to the deleted RegisteredUserController.');
    }

    public function test_the_root_cleanup_codemod_scripts_are_gone(): void
    {
        foreach (['cleanup-create.cjs', 'cleanup-create2.cjs', 'cleanup-create3.cjs', 'cleanup-create4.cjs'] as $script) {
            $this->assertFileDoesNotExist(base_path($script), "{$script} has no consumer and must stay deleted.");
        }
    }
}
