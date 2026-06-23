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
}
