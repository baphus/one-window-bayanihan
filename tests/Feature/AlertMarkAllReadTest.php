<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AlertMarkAllReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_mark_all_alerts_as_read(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            DB::table('alerts')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'test',
                'severity' => 'info',
                'title' => "Alert {$i}",
                'assigned_to_id' => $user->id,
                'created_at' => now(),
            ]);
        }

        $response = $this->actingAs($user)->postJson('/api/alerts/mark-all-read');

        $response->assertOk()
            ->assertJson(['success' => true, 'count' => 3]);

        $unreadCount = DB::table('alerts')
            ->where('assigned_to_id', $user->id)
            ->whereNull('read_at')
            ->count();
        $this->assertEquals(0, $unreadCount);
    }

    public function test_only_own_alerts_are_marked_read(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        DB::table('alerts')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'severity' => 'info',
            'title' => 'User 1 Alert',
            'assigned_to_id' => $user1->id,
            'created_at' => now(),
        ]);

        DB::table('alerts')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'severity' => 'info',
            'title' => 'User 2 Alert',
            'assigned_to_id' => $user2->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user1)->postJson('/api/alerts/mark-all-read');

        $response->assertOk()->assertJson(['success' => true, 'count' => 1]);

        $user2Unread = DB::table('alerts')
            ->where('assigned_to_id', $user2->id)
            ->whereNull('read_at')
            ->count();
        $this->assertEquals(1, $user2Unread);
    }

    public function test_mark_all_read_is_idempotent(): void
    {
        $user = User::factory()->create();

        DB::table('alerts')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'severity' => 'info',
            'title' => 'Alert',
            'assigned_to_id' => $user->id,
            'created_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/api/alerts/mark-all-read');
        $response = $this->actingAs($user)->postJson('/api/alerts/mark-all-read');

        $response->assertOk()->assertJson(['success' => true, 'count' => 0]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/alerts/mark-all-read');
        $response->assertStatus(401);
    }
}
