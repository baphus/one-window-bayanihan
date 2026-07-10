<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add service_id and name to servqual_configs
        Schema::table('servqual_configs', function (Blueprint $table) {
            $table->uuid('service_id')->nullable()->after('agency_id');
            $table->string('name')->nullable()->after('service_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
        });

        DB::statement('DROP INDEX IF EXISTS servqual_configs_active_agency_unique');
        DB::statement('DROP INDEX IF EXISTS servqual_configs_active_agency_default_unique');
        DB::statement('ALTER TABLE servqual_configs DROP CONSTRAINT IF EXISTS servqual_configs_agency_id_service_name_unique');
        DB::statement('DROP INDEX IF EXISTS servqual_configs_agency_id_service_name_unique');

        // 2. Add service_id to feedback_invitations
        Schema::table('feedback_invitations', function (Blueprint $table) {
            $table->uuid('service_id')->nullable()->after('referral_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('set null');
        });

        // 3. Add service_id to feedback
        Schema::table('feedback', function (Blueprint $table) {
            $table->uuid('service_id')->nullable()->after('referral_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('set null');
        });

        // 4. Normalize existing configs before adding partial uniqueness.
        DB::statement('
            UPDATE servqual_configs sc
            SET name = COALESCE(sc.name, sc.service_name, \'Default Feedback Form\')
            WHERE sc.name IS NULL
        ');

        DB::statement('
            UPDATE servqual_configs sc
            SET service_id = s.id
            FROM services s
            WHERE sc.service_name = s.name
              AND sc.agency_id = s.agcy_id
              AND sc.service_id IS NULL
        ');

        // Keep at most one service-specific config per agency/service before unique index creation.
        DB::statement('
            DELETE FROM servqual_configs sc
            USING (
                SELECT id,
                       ROW_NUMBER() OVER (PARTITION BY agency_id, service_id ORDER BY is_active DESC, created_at ASC, id ASC) AS rn
                FROM servqual_configs
                WHERE service_id IS NOT NULL
            ) ranked
            WHERE sc.id = ranked.id
              AND ranked.rn > 1
        ');

        // If multiple configs remain unassigned for an agency, preserve exactly one default.
        DB::statement('
            DELETE FROM servqual_configs sc
            USING (
                SELECT id,
                       ROW_NUMBER() OVER (PARTITION BY agency_id ORDER BY is_active DESC, created_at ASC, id ASC) AS rn
                FROM servqual_configs
                WHERE service_id IS NULL
            ) ranked
            WHERE sc.id = ranked.id
              AND ranked.rn > 1
        ');

        DB::statement('
            CREATE UNIQUE INDEX servqual_configs_agency_default_unique
            ON servqual_configs (agency_id) WHERE service_id IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX servqual_configs_agency_service_unique
            ON servqual_configs (agency_id, service_id) WHERE service_id IS NOT NULL
        ');

        DB::statement('CREATE INDEX feedback_agency_service_created_idx ON feedback (agency_id, service_id, created_at)');
        DB::statement('CREATE INDEX feedback_invitations_agency_service_created_idx ON feedback_invitations (agency_id, service_id, created_at)');

        // 5. Backfill service_id on feedback_invitations
        DB::statement('
            UPDATE feedback_invitations fi
            SET service_id = s.id
            FROM services s
            WHERE fi.service_name = s.name
              AND fi.agency_id = s.agcy_id
              AND fi.service_id IS NULL
        ');

        // 6. Backfill service_id on feedback
        DB::statement('
            UPDATE feedback f
            SET service_id = s.id
            FROM services s
            WHERE f.service_name = s.name
              AND f.agency_id = s.agcy_id
              AND f.service_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
        });

        Schema::table('feedback_invitations', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
        });

        DB::statement('DROP INDEX IF EXISTS servqual_configs_agency_service_unique');
        DB::statement('DROP INDEX IF EXISTS servqual_configs_agency_default_unique');
        DB::statement('DROP INDEX IF EXISTS feedback_agency_service_created_idx');
        DB::statement('DROP INDEX IF EXISTS feedback_invitations_agency_service_created_idx');

        DB::statement(
            'CREATE UNIQUE INDEX servqual_configs_active_agency_unique
             ON servqual_configs (agency_id) WHERE is_active = true'
        );

        Schema::table('servqual_configs', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
            $table->dropColumn('name');
        });

        DB::statement('
            DELETE FROM servqual_configs sc
            USING (
                SELECT id,
                       ROW_NUMBER() OVER (PARTITION BY agency_id, service_name ORDER BY is_active DESC, created_at ASC, id ASC) AS rn
                FROM servqual_configs
                WHERE service_name IS NOT NULL
            ) ranked
            WHERE sc.id = ranked.id
              AND ranked.rn > 1
        ');

        DB::statement('
            ALTER TABLE servqual_configs
            ADD CONSTRAINT servqual_configs_agency_id_service_name_unique
            UNIQUE (agency_id, service_name)
        ');
    }
};
