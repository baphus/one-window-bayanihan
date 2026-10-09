<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role;

        if ($role === null || ! in_array($role, $roles, true)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }

    /**
     * Backing values for use in route definitions, so a route file never
     * retypes a role literal: `->middleware('role:'.UserRole::ADMIN->value)`.
     */
    public static function allowed(): array
    {
        return UserRole::values();
    }
}
