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
        // PostgreSQL-specific session variables for RLS — skip in SQLite tests
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Ensure UTF-8 client encoding (Windows PDO driver may default to WIN1252)
            DB::statement('SET SESSION client_encoding TO UTF8');
            // Always (re)set every RLS variable, even for guests: the settings
            // below are session-scoped and Laravel reuses persistent connections
            // across requests, so a guest must overwrite — never inherit — the
            // previous request's values. Empty string matches no policy (fail-closed).
            // Use set_config with bound parameters to prevent SQL injection.
            // set_config accepts parameterized bindings (unlike raw SET SESSION).
            // Third arg (is_local) = false means session-scoped (survives the
            // statement's own implicit transaction); true would be discarded
            // the instant this statement finishes.
            $user = $request->user();
            $this->setConfig('app.current_user_id', $user ? (string) $user->id : '');
            $this->setConfig('app.user_role', $user ? (string) $user->role : '');
            $this->setConfig('app.client_request_id', $this->capabilityRequestId($request) ?? '');
            $this->setConfig('app.client_link_id', $this->capabilitySessionValue($request, 'link_id') ?? '');
        }

        return $next($request);
    }

    private function setConfig(string $name, string $value): void
    {
        DB::statement('SELECT set_config(?, ?, ?)', [$name, $value, false]);
    }

    private function capabilityRequestId(Request $request): ?string
    {
        return $this->capabilitySessionValue($request, 'request_id');
    }

    private function capabilitySessionValue(Request $request, string $key): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $session = $request->session()->get('client_request_access');

        return is_array($session) && isset($session[$key]) ? (string) $session[$key] : null;
    }
}
