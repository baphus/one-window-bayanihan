<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates one user per role so end-to-end flows can be exercised in a
 * non-production environment.
 *
 * Refuses to run when APP_ENV is production: it creates accounts whose
 * passwords come from an environment variable and are therefore known to
 * whoever ran the seeder.
 */
class StagingE2EUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('StagingE2EUserSeeder refuses to run in production.');

            return;
        }

        $password = env('STAGING_E2E_PASSWORD');

        if (! $password || strlen($password) < 16) {
            $this->command->error('STAGING_E2E_PASSWORD must be set and at least 16 characters.');

            return;
        }

        $agency = Agency::query()->orderBy('created_at')->first();

        if (! $agency) {
            $this->command->error('No agency found — run AgencySeeder first.');

            return;
        }

        $accounts = [
            [
                'email' => 'cm.e2e@bayanihan.gov.ph',
                'name' => 'E2E Case Manager',
                'role' => 'CASE_MANAGER',
                'agcy_id' => null,
            ],
            [
                'email' => 'agency.e2e@bayanihan.gov.ph',
                'name' => 'E2E Agency Officer',
                'role' => 'AGENCY',
                'agcy_id' => $agency->getKey(),
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::withoutGlobalScopes()->where('email', $account['email'])->first();

            if (! $user) {
                $user = new User();
                $user->id = (string) Str::uuid();
                $user->email = $account['email'];
            }

            $user->name = $account['name'];
            $user->password = $password;   // 'hashed' cast hashes on assignment
            $user->role = $account['role'];
            $user->agcy_id = $account['agcy_id'];
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->save();

            $this->command->info(sprintf(
                'ready: %s role=%s agcy_id=%s',
                $user->email,
                $user->role,
                $user->agcy_id ?? '(none)'
            ));
        }

        $this->command->info('Agency used: '.$agency->getKey());
    }
}
