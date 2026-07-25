<?php

namespace Tests\Unit;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Services\IntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntakeServiceDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private IntakeService $intakeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->intakeService = app(IntakeService::class);
    }

    /** @test */
    public function test_check_duplicate_returns_true_for_active_case(): void
    {
        $email = 'active-case@example.com';

        $client = Client::factory()->create(['email' => $email]);
        User::factory()->create([
            'email' => $email,
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        CaseFile::factory()->open()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);

        $result = $this->intakeService->checkDuplicate($email);

        $this->assertTrue($result['duplicate']);
        $this->assertNull($result['existing_client']);
        $this->assertNotNull($result['message']);
    }

    /** @test */
    public function test_check_duplicate_returns_false_for_closed_cases(): void
    {
        $email = 'closed-case@example.com';

        $client = Client::factory()->create(['email' => $email]);
        User::factory()->create([
            'email' => $email,
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        CaseFile::factory()->closed()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);

        $result = $this->intakeService->checkDuplicate($email);

        $this->assertFalse($result['duplicate']);
        $this->assertNotNull($result['existing_client']);
        $this->assertNull($result['message']);
    }

    /** @test */
    public function test_check_duplicate_returns_false_for_no_match(): void
    {
        $result = $this->intakeService->checkDuplicate('nonexistent@example.com');

        $this->assertFalse($result['duplicate']);
        $this->assertNull($result['existing_client']);
        $this->assertNull($result['message']);
    }
}
