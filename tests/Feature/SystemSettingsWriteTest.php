<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SystemSettingsWriteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HandleInertiaRequests::class);

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    #[Test]
    public function clearing_referral_overdue_days_keeps_the_stored_value(): void
    {
        SystemSetting::setValue('referral_overdue_days', 30, 'notifications', 'Seeded description');

        foreach ([null, ''] as $emptyValue) {
            $response = $this->actingAs($this->admin)
                ->post('/admin/system-settings', ['referral_overdue_days' => $emptyValue]);

            $response->assertRedirect();
            $response->assertSessionHas('success', 'Settings updated successfully.');
            $response->assertSessionHasNoErrors();
        }

        $this->assertSame('30', SystemSetting::getValue('referral_overdue_days'));
    }

    #[Test]
    public function clearing_referral_overdue_days_never_writes_zero(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/system-settings', ['referral_overdue_days' => null])
            ->assertRedirect();

        $this->assertFalse(
            SystemSetting::query()->where('key', 'referral_overdue_days')->exists(),
            'A cleared field must not create a row.',
        );

        // Consumers cast to int and compare against created_at; a stored 0 marks
        // every active referral as overdue and mails everyone.
        $this->assertGreaterThanOrEqual(1, (int) SystemSetting::getValue('referral_overdue_days', 7));
    }

    #[Test]
    public function a_valid_referral_overdue_days_value_is_stored(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/system-settings', ['referral_overdue_days' => 14]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Settings updated successfully.');
        $response->assertSessionHasNoErrors();

        $this->assertSame('14', SystemSetting::getValue('referral_overdue_days'));
    }

    #[Test]
    public function referral_overdue_days_below_one_is_rejected_by_validation(): void
    {
        SystemSetting::setValue('referral_overdue_days', 30);

        foreach ([0, -1, '0'] as $rejected) {
            $response = $this->actingAs($this->admin)
                ->post('/admin/system-settings', ['referral_overdue_days' => $rejected]);

            $response->assertSessionHasErrors('referral_overdue_days');
        }

        $this->assertSame('30', SystemSetting::getValue('referral_overdue_days'));
    }

    #[Test]
    public function updating_a_setting_preserves_its_seeded_category_and_description(): void
    {
        SystemSetting::query()->create([
            'key' => 'referral_overdue_days',
            'value' => '10',
            'category' => 'notifications',
            'description' => 'Days before an active referral counts as overdue',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/system-settings', ['referral_overdue_days' => 21])
            ->assertRedirect();

        $setting = SystemSetting::query()->findOrFail('referral_overdue_days');

        $this->assertSame('21', $setting->value);
        $this->assertSame('notifications', $setting->category);
        $this->assertSame('Days before an active referral counts as overdue', $setting->description);
    }

    #[Test]
    public function chatbot_reindex_returns_graceful_json_when_the_command_throws(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('chatbot:index')
            ->andThrow(new RuntimeException('Helpdesk corpus is missing.'));

        Log::spy();

        $response = $this->actingAs($this->admin)
            ->post('/admin/system-settings/reindex-chatbot');

        $response->assertStatus(500);
        $response->assertExactJson([
            'success' => false,
            'message' => 'Helpdesk cache refresh failed. Please try again.',
        ]);

        // Without the Log facade import the catch block fatals before it can
        // respond, so the failure never reaches the logging call.
        Log::shouldHaveReceived('error')->once();
    }

    #[Test]
    public function chatbot_reindex_returns_graceful_json_when_the_command_exits_non_zero(): void
    {
        Artisan::shouldReceive('call')->once()->with('chatbot:index')->andReturn(1);

        $response = $this->actingAs($this->admin)
            ->post('/admin/system-settings/reindex-chatbot');

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Helpdesk cache refresh failed. Check the server logs for details.',
        ]);

        $this->assertNull(SystemSetting::getValue('chatbot_last_reindexed_at'));
    }
}
