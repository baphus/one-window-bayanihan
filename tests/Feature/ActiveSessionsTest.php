<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActiveSessionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HandleInertiaRequests::class);

        Role::create(['name' => 'ADMIN']);
        $this->admin = User::factory()->create(['role' => 'ADMIN']);
        $this->admin->assignRole('ADMIN');
    }

    #[Test]
    public function test_page_loads(): void
    {
        $sessionId = session()->getId();

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $this->admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->get('/admin/system/active-sessions');

        $response->assertOk();
        $response->assertJsonStructure(['component', 'props' => ['sessions']]);
        $this->assertSame('Admin/ActiveSessions/Index', $response->json('component'));
    }

    #[Test]
    public function test_terminate_session(): void
    {
        $sessionId = 'other-session-id';
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $this->admin->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Unit Test',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/system/active-sessions/'.$sessionId.'/terminate');

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Session terminated.');
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
    }
}
