<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $casesEnum = $this->getEnumType('cases', 'status');
            if ($casesEnum) {
                DB::statement('ALTER TABLE cases ALTER COLUMN status TYPE VARCHAR(50) USING status::text');
                DB::statement("DROP TYPE IF EXISTS \"{$casesEnum}\"");
            }

            $referralsEnum = $this->getEnumType('referrals', 'status');
            if ($referralsEnum) {
                DB::statement('ALTER TABLE referrals ALTER COLUMN status TYPE VARCHAR(50) USING status::text');
                DB::statement("DROP TYPE IF EXISTS \"{$referralsEnum}\"");
            }
        } else {
            DB::statement('ALTER TABLE cases ALTER COLUMN status TYPE VARCHAR(50)');
            DB::statement('ALTER TABLE referrals ALTER COLUMN status TYPE VARCHAR(50)');
        }

        DB::table('system_settings')->insert([
            'key' => 'referral_overdue_days',
            'value' => '7',
        ]);
    }

    private function getEnumType(string $table, string $column): ?string
    {
        $result = DB::select("
            SELECT t.typname AS enum_type
            FROM pg_attribute a
            JOIN pg_type t ON t.oid = a.atttypid
            WHERE a.attrelid = '{$table}'::regclass
              AND a.attname = '{$column}'
              AND t.typtype = 'e'
        ");

        return ! empty($result) ? $result[0]->enum_type : null;
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'referral_overdue_days')->delete();
    }
};
