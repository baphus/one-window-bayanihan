<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgencyDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'ADMIN']);
    }

    #[Test]
    public function cannot_delete_default_agency(): void
    {
        $dmw = Agency::factory()->create([
            'name' => 'DMW',
            'short' => 'DMW',
            'slug' => 'dmw',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.agencies.destroy', $dmw->id));

        $response->assertStatus(422);
    }

    #[Test]
    public function can_delete_non_default_agency(): void
    {
        $dmw = Agency::factory()->create([
            'name' => 'DMW',
            'short' => 'DMW',
            'slug' => 'dmw',
            'is_default' => true,
        ]);

        $agency = Agency::factory()->create([
            'name' => 'Other Agency',
            'short' => 'Other',
            'slug' => 'other',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.agencies.destroy', $agency->id));

        $response->assertStatus(302);
    }

    #[Test]
    public function cannot_unset_is_default_on_only_default(): void
    {
        $dmw = Agency::factory()->create([
            'name' => 'DMW',
            'short' => 'DMW',
            'slug' => 'dmw',
            'is_default' => true,
        ]);

        // Another non-default agency exists but does not change the default count
        Agency::factory()->create([
            'name' => 'Other Agency',
            'short' => 'Other',
            'slug' => 'other',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.agencies.update', $dmw->id), [
                'name' => 'DMW',
                'short' => 'DMW',
                'is_default' => false,
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function can_edit_other_fields_on_default_agency(): void
    {
        $dmw = Agency::factory()->create([
            'name' => 'DMW',
            'short' => 'DMW',
            'slug' => 'dmw',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.agencies.update', $dmw->id), [
                'name' => 'Renamed Agency',
                'short' => 'DMW',
            ]);

        $response->assertStatus(302);
    }
}
