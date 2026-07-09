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

        $adminEmail = 'admin@bayanihan.gov.ph';
        DB::table('users')->updateOrInsert(
            ['email' => $adminEmail],
            [
                'id' => DB::table('users')->where('email', $adminEmail)->value('id') ?? (string) Str::uuid(),
                'name' => 'System Administrator',
                'password' => Hash::make('P@ssw0rd!'),
                'role' => 'ADMIN',
                'is_active' => true,
                'email_verified_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }
}
