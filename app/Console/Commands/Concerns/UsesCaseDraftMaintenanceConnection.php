<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

trait UsesCaseDraftMaintenanceConnection
{
    private function useCaseDraftMaintenanceConnection(): void
    {
        $url = (string) config('database.connections.case_draft_maintenance.url');
        $role = (string) config('database.rls.maintenance_role');
        if ($url === '' || $role === '') {
            throw new RuntimeException('CASE_DRAFT_MAINT_DB_URL and DB_MAINTENANCE_ROLE are required.');
        }

        DB::purge('case_draft_maintenance');
        $connection = DB::connection('case_draft_maintenance');
        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Case-draft maintenance requires PostgreSQL.');
        }

        $actualRole = (string) $connection->selectOne('select current_user')->current_user;
        if ($actualRole !== $role) {
            throw new RuntimeException('Maintenance connection is not using the configured maintenance role.');
        }

        config(['database.default' => 'case_draft_maintenance']);
    }
}
