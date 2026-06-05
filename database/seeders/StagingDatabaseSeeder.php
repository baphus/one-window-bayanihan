<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Comprehensive test data seeder for staging environment.
 * Generates 1000+ realistic case records with full relationships.
 *
 * Run: php artisan seed:staging
 */
class StagingDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database for staging.
     */
    public function run(): void
    {
        $this->command->info('Seeding staging database with comprehensive test data...');

        // 1. Create test users by role
        $this->createTestUsers();

        // 2. Seed core lookups (services, agencies, categories)
        $this->call([
            ServiceSeeder::class,
            AgencySeeder::class,
            CaseCategorySeeder::class,
            SystemSettingSeeder::class,
        ]);

        // 3. Create realistic clients and cases
        $this->createClientsAndCases();

        // 4. Create referrals with relationships
        $this->createReferrals();

        // 5. Create audit log history
        $this->createAuditLogs();

        $this->command->info('✓ Staging database seeding complete!');
    }

    /**
     * Create test users with different roles.
     */
    private function createTestUsers(): void
    {
        $this->command->line('→ Creating test users...');

        $roles = ['CASE_MANAGER', 'AGENCY', 'ADMIN'];
        $basePasswords = Hash::make('123456'); // Test password for all users

        foreach ($roles as $role) {
            for ($i = 1; $i <= 3; $i++) {
                User::firstOrCreate(
                    ['email' => strtolower($role) . $i . '@test.gov.ph'],
                    [
                        'name' => ucfirst($role) . ' User ' . $i,
                        'email' => strtolower($role) . $i . '@test.gov.ph',
                        'email_verified_at' => now(),
                        'password' => $basePasswords,
                        'role' => $role,
                        'contact_number' => fake()->phoneNumber(),
                        'remember_token' => null,
                    ]
                );
            }
        }

        $this->command->line('  ✓ Created 9 test users (3 per role)');
    }

    /**
     * Create 1000+ realistic clients and cases.
     */
    private function createClientsAndCases(): void
    {
        $this->command->line('→ Creating clients and cases (this may take 1-2 minutes)...');

        $agencies = Agency::all();
        $categories = CaseCategory::all();
        $caseManagers = User::where('role', 'CASE_MANAGER')->get();

        $statuses = ['OPEN', 'IN_PROGRESS', 'PENDING_REFERRAL', 'REFERRED', 'CLOSED', 'ARCHIVED'];
        $totalCases = 1250; // Aim for 1000+
        $batchSize = 100;

        for ($batch = 0; $batch < ceil($totalCases / $batchSize); $batch++) {
            $clientsToCreate = min($batchSize, $totalCases - ($batch * $batchSize));

            for ($i = 0; $i < $clientsToCreate; $i++) {
                $client = Client::factory()->create();

                // Create 1-3 cases per client for variety
                $numCases = fake()->randomElement([1, 1, 1, 2, 2, 3]);
                for ($c = 0; $c < $numCases; $c++) {
                    CaseFile::factory()->create([
                        'client_id' => $client->id,
                        'user_id' => $caseManagers->random()->id,
                        'category_id' => $categories->random()->id,
                        'status' => fake()->randomElement($statuses),
                    ]);
                }
            }

            $this->command->line("  ✓ Batch " . ($batch + 1) . " complete");
        }

        $caseCount = CaseFile::count();
        $this->command->line("  ✓ Created " . $caseCount . " cases with realistic relationships");
    }

    /**
     * Create referrals for cases.
     */
    private function createReferrals(): void
    {
        $this->command->line('→ Creating referrals and relationships...');

        $services = Service::all();
        $agencies = Agency::all();

        // Get referred and open cases
        $casesToRefer = CaseFile::whereIn('status', ['PENDING_REFERRAL', 'REFERRED', 'OPEN'])
            ->inRandomOrder()
            ->limit(600)
            ->get();

        foreach ($casesToRefer as $case) {
            $numReferrals = fake()->randomElement([1, 1, 2, 2, 3]);

            for ($i = 0; $i < $numReferrals; $i++) {
                $requiredServices = $services->random(fake()->numberBetween(1, 3))
                    ->pluck('id')
                    ->toArray();

                Referral::factory()->create([
                    'case_id' => $case->id,
                    'agcy_id' => $agencies->random()->id,
                    'required_services' => json_encode($requiredServices),
                    'status' => fake()->randomElement(['PENDING', 'ACCEPTED', 'COMPLETED', 'REJECTED']),
                    'notes' => fake()->paragraph(2),
                ]);
            }
        }

        $referralCount = Referral::count();
        $this->command->line('  ✓ Created ' . $referralCount . ' referrals');
    }

    /**
     * Create audit log history.
     */
    private function createAuditLogs(): void
    {
        $this->command->line('→ Creating audit logs...');

        $users = User::all();
        $actions = ['CREATE', 'UPDATE', 'DELETE', 'VIEW', 'CLOSE', 'REOPEN', 'ASSIGN', 'REFER', 'COMMENT'];
        $modules = ['cases', 'referrals', 'clients', 'agencies', 'users'];

        // Create 300-500 audit logs for realistic history
        AuditLog::factory()
            ->count(400)
            ->create([
                'user_id' => $users->random()->id,
            ]);

        $auditCount = AuditLog::count();
        $this->command->line('  ✓ Created ' . $auditCount . ' audit logs');
    }
}
