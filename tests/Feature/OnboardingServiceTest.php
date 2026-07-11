<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_onboarding_required_returns_true_when_null(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => null,
        ]);

        $service = app(OnboardingService::class);
        $result = $service->isOnboardingRequired($user);

        $this->assertTrue($result);
    }

    public function test_is_onboarding_required_returns_false_when_set(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => now(),
        ]);

        $service = app(OnboardingService::class);
        $result = $service->isOnboardingRequired($user);

        $this->assertFalse($result);
    }

    public function test_mark_onboarding_complete_sets_timestamp(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => null,
            'onboarding_step' => 'step-3',
        ]);

        $service = app(OnboardingService::class);
        $service->markOnboardingComplete($user);
        $user->refresh();

        $this->assertNotNull($user->onboarding_completed_at);
        $this->assertNull($user->onboarding_step);
    }

    public function test_skip_onboarding_sets_timestamp(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => null,
            'onboarding_step' => 'step-2',
        ]);

        $service = app(OnboardingService::class);
        $service->skipOnboarding($user);
        $user->refresh();

        $this->assertNotNull($user->onboarding_completed_at);
        $this->assertNull($user->onboarding_step);
    }

    public function test_reset_onboarding_clears_columns(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => now(),
            'onboarding_step' => 'step-5',
        ]);

        $service = app(OnboardingService::class);
        $service->markOnboardingComplete($user);
        $user->refresh();
        $this->assertNotNull($user->onboarding_completed_at);

        $service->resetOnboarding($user);
        $user->refresh();

        $this->assertNull($user->onboarding_completed_at);
        $this->assertNull($user->onboarding_step);
    }

    public function test_update_step_sets_step_value(): void
    {
        $user = User::factory()->create([
            'onboarding_step' => null,
        ]);

        $service = app(OnboardingService::class);
        $service->updateStep($user, 'step-3');
        $user->refresh();

        $this->assertEquals('step-3', $user->onboarding_step);
    }

    public function test_update_step_with_null_clears_step(): void
    {
        $user = User::factory()->create([
            'onboarding_step' => 'step-3',
        ]);

        $service = app(OnboardingService::class);
        $service->updateStep($user, null);
        $user->refresh();

        $this->assertNull($user->onboarding_step);
    }

    public function test_get_onboarding_state_returns_correct_structure(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => null,
            'onboarding_step' => 'step-2',
        ]);

        $service = app(OnboardingService::class);
        $state = $service->getOnboardingState($user);

        $this->assertIsArray($state);
        $this->assertArrayHasKey('required', $state);
        $this->assertArrayHasKey('step', $state);
        $this->assertArrayHasKey('completed_at', $state);
        $this->assertTrue($state['required']);
        $this->assertEquals('step-2', $state['step']);
        $this->assertNull($state['completed_at']);
    }

    public function test_get_onboarding_state_returns_false_when_completed(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => now(),
            'onboarding_step' => null,
        ]);

        $service = app(OnboardingService::class);
        $state = $service->getOnboardingState($user);

        $this->assertFalse($state['required']);
        $this->assertNull($state['step']);
        $this->assertNotNull($state['completed_at']);
    }

    public function test_parse_step_accepts_valid_key(): void
    {
        $service = app(OnboardingService::class);

        $this->assertEquals(['page' => 2, 'step' => 1], $service->parseStep('2:1'));
        $this->assertEquals(['page' => 0, 'step' => 0], $service->parseStep('0:0'));
    }

    public function test_parse_step_rejects_corrupt_values(): void
    {
        $service = app(OnboardingService::class);

        $this->assertNull($service->parseStep(null));
        $this->assertNull($service->parseStep('legacy-step-3'));
        $this->assertNull($service->parseStep('1:'));
        $this->assertNull($service->parseStep(':2'));
        $this->assertNull($service->parseStep('-1:2'));
        $this->assertNull($service->parseStep('1:2:3'));
    }

    public function test_mark_guide_seen_is_idempotent(): void
    {
        $user = User::factory()->create(['seen_page_guides' => null]);
        $service = app(OnboardingService::class);

        $service->markGuideSeen($user, 'cases.index');
        $service->markGuideSeen($user, 'cases.index');
        $service->markGuideSeen($user, 'reports.index');
        $user->refresh();

        $this->assertEquals(['cases.index', 'reports.index'], $user->seen_page_guides);
    }

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

    public function test_get_onboarding_state_defaults_new_fields(): void
    {
        $user = User::factory()->create([
            'seen_page_guides' => null,
            'checklist_progress' => null,
        ]);

        $state = app(OnboardingService::class)->getOnboardingState($user);

        $this->assertEquals([], $state['seen_page_guides']);
        $this->assertEquals(['items' => [], 'dismissed_at' => null], $state['checklist_progress']);
    }
}
