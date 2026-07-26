<?php

namespace App\Console\Commands;

use App\Models\CaseFile;
use App\Models\Client;
use Illuminate\Console\Command;

class SeedQueueTest extends Command
{
    protected $signature = 'app:seed-queue-test';

    protected $description = 'Seed test intake queue data for E2E verification';

    public function handle()
    {
        $c1 = Client::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'sex' => 'FEMALE', 'date_of_birth' => '1990-05-15', 'contact_number' => '+639171234567', 'email' => 'maria.santos@test.com', 'nationality' => 'Filipino']);
        $c2 = Client::create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'sex' => 'MALE', 'date_of_birth' => '1985-08-20', 'contact_number' => '+639181234567', 'email' => null, 'nationality' => 'Filipino']);
        $c3 = Client::create(['first_name' => 'Ana', 'last_name' => 'Reyes', 'sex' => 'FEMALE', 'date_of_birth' => '1995-12-01', 'contact_number' => '+639191234567', 'email' => 'ana.reyes@test.com', 'nationality' => 'Filipino']);

        CaseFile::factory()->draft()->create([
            'client_id' => $c1->id,
            'source' => 'self_filed',
            'vulnerability_indicator' => 'PWD',
            'summary' => 'Maria Santos is seeking legal assistance regarding an employment dispute with her previous overseas employer.',
            'user_id' => null,
        ]);

        CaseFile::factory()->draft()->create([
            'client_id' => $c2->id,
            'source' => 'self_filed',
            'vulnerability_indicator' => 'Senior Citizen',
            'summary' => 'Juan Dela Cruz requires medical assistance after an accident while working abroad.',
            'user_id' => null,
        ]);

        CaseFile::factory()->draft()->create([
            'client_id' => $c3->id,
            'source' => 'self_filed',
            'vulnerability_indicator' => 'Solo Parent',
            'summary' => 'Ana Reyes is requesting repatriation assistance after her contract was terminated.',
            'user_id' => null,
            'created_at' => now()->subDays(3),
        ]);

        $this->info('Created 3 test intake cases');

        return 0;
    }
}
