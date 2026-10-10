<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SoftDeleteFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_delete_persists_flags_in_a_single_update(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        DB::enableQueryLog();
        $user->delete();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $updates = array_filter(
            array_column($queries, 'query'),
            fn (string $sql) => preg_match('/^update /i', $sql) === 1
        );

        // The flag columns ride the same UPDATE as deleted_at — not a
        // saveQuietly() write followed by SoftDeletes' own update.
        $this->assertCount(1, $updates, 'Soft delete issued more than one UPDATE.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_deleted' => true,
            'deleted_by' => $user->id,
        ]);
    }

    public function test_soft_delete_without_auth_context_leaves_deleted_by_null(): void
    {
        $user = User::factory()->create();

        // No authenticated user (simulates CLI/queue context).
        $user->delete();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_deleted' => true,
            'deleted_by' => null,
        ]);
    }
}
