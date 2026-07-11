<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\SetPostgresSession;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable SetPostgresSession middleware which uses parameterized SET SESSION
        // statements that PostgreSQL rejects with PDO prepared statements
        $this->withoutMiddleware(SetPostgresSession::class);

        Mail::fake();

        $this->user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_init_requires_authentication(): void
    {
        $response = $this->get(route('profile.email-change.init'));

        $response->assertRedirect('/login');
    }

    public function test_init_returns_email_change_step(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->get(route('profile.email-change.init'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('email_change_step', 'new-email')
        );
    }

    public function test_send_otp_requires_authentication(): void
    {
        $response = $this->post(route('profile.email-change.send-otp'), [
            'new_email' => 'new@example.com',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_send_otp_requires_password_and_email(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->from(route('profile.edit'))
            ->post(route('profile.email-change.send-otp'), []);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors(['password', 'new_email']);
    }

    public function test_send_otp_rejects_wrong_password(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->from(route('profile.edit'))
            ->post(route('profile.email-change.send-otp'), [
                'password' => 'wrong-password',
                'new_email' => 'new@example.com',
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors(['password']);
    }

    public function test_verify_otp_requires_authentication(): void
    {
        $response = $this->post(route('profile.email-change.verify-otp'), [
            'new_email' => 'new@example.com',
            'otp' => '123456',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_verify_otp_changes_email_and_logs_audit(): void
    {
        $this->actingAs($this->user);

        $this->mock(OtpService::class, function ($mock) {
            $mock->shouldReceive('verify')
                ->once()
                ->andReturn(true);
        });

        $response = $this->post(route('profile.email-change.verify-otp'), [
            'new_email' => 'new@example.com',
            'otp' => '123456',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('profile.edit'));

        $this->user->refresh();
        $this->assertEquals('new@example.com', $this->user->email);

        $audit = AuditLog::where('module', 'user')
            ->where('action', 'UPDATE')
            ->where('entity_id', $this->user->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('old@example.com', $audit->old_value['email']);
        $this->assertEquals('new@example.com', $audit->new_value['email']);
    }

    public function test_verify_otp_fails_with_invalid_otp(): void
    {
        $this->actingAs($this->user);

        $this->mock(OtpService::class, function ($mock) {
            $mock->shouldReceive('verify')
                ->once()
                ->andReturn(false);
        });

        $response = $this
            ->from(route('profile.edit'))
            ->post(route('profile.email-change.verify-otp'), [
                'new_email' => 'new@example.com',
                'otp' => '000000',
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors(['otp']);

        $this->user->refresh();
        $this->assertEquals('old@example.com', $this->user->email);
    }
}
