<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set PostgreSQL session variables for Row-Level Security (RLS) context.
 *
 * This middleware propagates the authenticated user's ID and role to the
 * PostgreSQL session, enabling RLS policies to perform per-user access checks
 * via current_setting('app.current_user_id') and current_setting('app.user_role').
 *
 * Requires a direct database connection (not PgBouncer transaction mode) so
 * that SET SESSION variables persist across queries within the request.
 */
class SetPostgresSession
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            // PostgreSQL-specific session variables for RLS — skip in SQLite tests
            if (DB::connection()->getDriverName() === 'pgsql') {
                // Ensure UTF-8 client encoding (Windows PDO driver may default to WIN1252)
                DB::statement('SET SESSION client_encoding TO UTF8');
                // Use set_config with bound parameters to prevent SQL injection.
                // set_config accepts parameterized bindings (unlike raw SET SESSION).
                // Third arg (is_local) = true means session-scoped.
                DB::statement('SELECT set_config(?, ?, ?)', [
                    'app.current_user_id',
                    (string) $user->id,
                    true,
                ]);
                DB::statement('SELECT set_config(?, ?, ?)', [
                    'app.user_role',
                    (string) $user->role,
                    true,
                ]);
            }
        }

        return $next($request);
    }
}
