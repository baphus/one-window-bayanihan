<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/** Establishes a transaction-local RLS identity for the draft aggregate. */
final class SetCaseDraftRlsContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            return $next($request);
        }

        $user = $request->user();
        if (! $user || (string) $user->role !== 'CASE_MANAGER') {
            throw new RuntimeException('Case-draft RLS requires an authenticated case manager.');
        }

        $runtimeRole = (string) config('database.rls.runtime_role');
        $databaseRole = (string) $connection->selectOne('select current_user')->current_user;
        if ($runtimeRole === '' || $databaseRole !== $runtimeRole) {
            throw new RuntimeException('Case-draft runtime connection is not using the configured non-owner role.');
        }

        $connection->beginTransaction();
        try {
            $connection->statement('select set_config(?, ?, true)', ['app.current_user_id', (string) $user->id]);
            $connection->statement('select set_config(?, ?, true)', ['app.user_role', (string) $user->role]);

            $response = $next($request);
            $connection->commit();

            return $response;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
