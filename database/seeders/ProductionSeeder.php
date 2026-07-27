<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ────────────────────────────────────────────
        // 1. Create admin user (idempotent via updateOrInsert)
        // ────────────────────────────────────────────

        $adminEmail = env('ADMIN_SEED_EMAIL', 'admin@bayanihan.gov.ph');

        // Columns that are safe to reconcile on every run. The password is
        // deliberately absent: this seeder previously used updateOrInsert with a
        // hardcoded Hash::make('P@ssw0rd!'), which meant re-running it against an
        // environment where the administrator password had been rotated silently
        // reset that account back to a value published in the repository. Every
        // environment seeded from this file also shared the same credential.
        $attributes = [
            'name' => 'System Administrator',
            'role' => 'ADMIN',
            'is_active' => true,
            'email_verified_at' => $now,
            'updated_at' => $now,
        ];

        $existing = DB::table('users')->where('email', $adminEmail)->first();

        if ($existing !== null) {
            // Never touch an existing administrator's password.
            DB::table('users')->where('email', $adminEmail)->update($attributes);

            return;
        }

        // First-time creation only. Prefer an operator-supplied secret; otherwise
        // mint a random one and surface it once, so an unattended run can never
        // leave a guessable administrator behind.
        $password = (string) env('ADMIN_SEED_PASSWORD', '');
        $generated = false;

        if ($password === '') {
            $password = Str::password(32);
            $generated = true;
        }

        DB::table('users')->insert($attributes + [
            'email' => $adminEmail,
            'id' => (string) Str::uuid(),
            'password' => Hash::make($password),
            'onboarding_completed_at' => null,
            'onboarding_step' => null,
            'seen_page_guides' => null,
            'checklist_progress' => null,
            'created_at' => $now,
        ]);

        if ($generated && $this->command !== null) {
            $this->command->warn("Generated administrator password for {$adminEmail}: {$password}");
            $this->command->warn('Record it in the password manager now — it is not stored anywhere else.');
        }

        // ────────────────────────────────────────────
        // 2. Seed agencies (idempotent — uses updateOrInsert by slug)
        // ────────────────────────────────────────────

        $this->call(AgencySeeder::class);
    }
}
