<?php

namespace Database\Seeders;

use App\Helpers\DefaultServqualQuestions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServqualConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $defaultQuestions = DefaultServqualQuestions::get();

        $agencies = DB::table('agencies')->select('id', 'slug', 'short')->get();

        $count = 0;
        foreach ($agencies as $agency) {
            $exists = DB::table('servqual_configs')
                ->where('agency_id', $agency->id)
                ->exists();

            if ($exists) {
                $this->command->info("  Skipping {$agency->short} (already has config)");

                continue;
            }

            $serviceName = DB::table('services')
                ->where('agcy_id', $agency->id)
                ->orderBy('name')
                ->value('name') ?? 'General Assistance';

            DB::table('servqual_configs')->insert([
                'id' => (string) Str::uuid(),
                'agency_id' => $agency->id,
                'name' => 'Default Feedback Form',
                'service_name' => $serviceName,
                'questions' => json_encode($defaultQuestions),
                'is_active' => true,
                'activated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $count++;
            $this->command->info("  Created servqual config for {$agency->short}");
        }

        $this->command->info("Created {$count} servqual config(s).");
    }
}
