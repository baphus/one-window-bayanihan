<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Carry the old reviewer reference onto the new column before the
            // drop migration removes intake_reviewed_by.
            DB::table('cases')
                ->whereNotNull('intake_reviewed_by')
                ->whereNull('reviewed_by')
                ->update(['reviewed_by' => DB::raw('intake_reviewed_by')]);

            // Every client without a linked account gets a stub OFW user so no
            // phantom (user-less) client survives the redesign. Stubs are
            // ACTIVE with unverified email + incomplete profile: LoginRequest
            // blocks only inactive/deleted, so is_active=true lets password
            // reset succeed; the `verified` middleware on /dashboard then
            // gates on email_verified_at=null until they verify. Entry via
            // password.request reset, then verify-email flow.
            // Draft-derived clients are built first (below) so this pass also
            // covers draft rows whose case had no filing user.
            DB::table('cases')
                ->whereNull('client_id')
                ->whereNotNull('draft_client_data')
                ->orderBy('id')
                ->chunkById(200, function ($cases) {
                    foreach ($cases as $case) {
                        $draft = is_string($case->draft_client_data)
                            ? json_decode($case->draft_client_data, true)
                            : (array) $case->draft_client_data;

                        $clientId = (string) Str::uuid();
                        $now = now();

                        DB::table('clients')->insert([
                            'id' => $clientId,
                            'type' => 'OFW',
                            'user_id' => $case->user_id,
                            'first_name' => $draft['first_name'] ?? '',
                            'last_name' => $draft['last_name'] ?? '',
                            'middle_name' => $draft['middle_name'] ?? null,
                            'suffix' => $draft['suffix'] ?? null,
                            'date_of_birth' => $draft['date_of_birth'] ?? null,
                            'sex' => ! empty($draft['sex']) ? strtoupper($draft['sex']) : null,
                            'email' => $draft['email'] ?? null,
                            'contact_number' => $draft['contact_number'] ?? null,
                            'is_deleted' => false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        DB::table('cases')
                            ->where('id', $case->id)
                            ->update(['client_id' => $clientId, 'updated_at' => $now]);
                    }
                });

            DB::table('clients')
                ->whereNull('user_id')
                ->orderBy('id')
                ->chunkById(200, function ($clients) {
                    foreach ($clients as $client) {
                        $userId = (string) Str::uuid();
                        $now = now();

                        DB::table('users')->insert([
                            'id' => $userId,
                            'name' => trim($client->first_name.' '.$client->last_name),
                            'email' => $client->id.'@stub.local',
                            'password' => Hash::make(Str::random(48)),
                            'role' => 'OFW',
                            'client_id' => $client->id,
                            'is_active' => true,
                            'email_verified_at' => null,
                            'profile_completed_at' => null,
                            'is_deleted' => false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        DB::table('clients')
                            ->where('id', $client->id)
                            ->update(['user_id' => $userId, 'updated_at' => $now]);
                    }
                });
        });
    }

    public function down(): void
    {
        // Data backfill is not reversible: stub users cannot be told apart
        // from real ones after the fact. Schema reversal lives in the
        // surrounding migrations; data stays.
    }
};
