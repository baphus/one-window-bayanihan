<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('model_has_roles') || ! Schema::hasTable('roles') || ! Schema::hasTable('users')) {
            return;
        }

        $roleMap = [
            'ADMIN' => 'ADMIN',
            'CASE_MANAGER' => 'CASE_MANAGER',
            'AGENCY_FOCAL_PERSON' => 'AGENCY',
        ];

        $assignments = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->select('model_has_roles.model_id', 'roles.name')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->get();

        foreach ($assignments as $assignment) {
            $mappedRole = $roleMap[$assignment->name] ?? null;
            if (! $mappedRole) {
                continue;
            }

            DB::table('users')
                ->where('id', $assignment->model_id)
                ->where(function ($query) use ($mappedRole) {
                    $query->whereNull('role')->orWhere('role', '!=', $mappedRole);
                })
                ->update(['role' => $mappedRole]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: role backfill has no safe rollback.
    }
};
