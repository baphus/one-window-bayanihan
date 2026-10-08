<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_checklist_item_first_timestamp_wins(): void
    {
        $user = User::factory()->create(['checklist_progress' => null]);
        $service = app(OnboardingService::class);

        $service->markChecklistItem($user, 'create-first-case');
        $user->refresh();
        $first = $user->checklist_progress['items']['create-first-case'];

        $service->markChecklistItem($user, 'create-first-case');
        $user->refresh();

        $this->assertEquals($first, $user->checklist_progress['items']['create-first-case']);
    }

    public function test_mark_checklist_item_quietly_swallows_failures(): void
    {
        $service = app(OnboardingService::class);

        // Null user is a no-op, not an error
        $service->markChecklistItemQuietly(null, 'create-first-case');

        $this->assertTrue(true);
    }

    public function test_dismiss_checklist_preserves_items(): void
    {
        $user = User::factory()->create([
            'checklist_progress' => ['items' => ['visit-reports' => '2026-07-11T00:00:00Z'], 'dismissed_at' => null],
        ]);
        $service = app(OnboardingService::class);

        $service->dismissChecklist($user);
        $user->refresh();

        $this->assertNotNull($user->checklist_progress['dismissed_at']);
        $this->assertArrayHasKey('visit-reports', $user->checklist_progress['items']);
    }

    public function test_mark_checklist_item_is_capped(): void
    {
        $items = [];
        foreach (range(1, OnboardingService::MAX_CHECKLIST_ITEMS) as $i) {
            $items["item-$i"] = '2026-07-11T00:00:00Z';
        }
        $user = User::factory()->create(['checklist_progress' => ['items' => $items, 'dismissed_at' => null]]);

        app(OnboardingService::class)->markChecklistItem($user, 'one-more');
        $user->refresh();

        $this->assertCount(OnboardingService::MAX_CHECKLIST_ITEMS, $user->checklist_progress['items']);
        $this->assertArrayNotHasKey('one-more', $user->checklist_progress['items']);
    }

    public function test_get_onboarding_state_defaults_checklist_progress(): void
    {
        $user = User::factory()->create([
            'checklist_progress' => null,
        ]);

        $state = app(OnboardingService::class)->getOnboardingState($user);

        $this->assertEquals(['checklist_progress' => ['items' => [], 'dismissed_at' => null]], $state);
    }
}
