<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\OtpMail;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function credentials(): array
    {
        return ['email' => 'ofw@example.test', 'password' => 'Str0ngPass!123', 'password_confirmation' => 'Str0ngPass!123'];
    }

    public function test_credentials_are_pending_and_password_is_hashed_until_otp_success(): void
    {
        $this->post(route('register.store'), $this->credentials())->assertRedirect(route('register.review'));
        $this->assertSame(['credentials'], array_keys(session('pending_registration')));
        $this->assertSame(['email', 'password'], array_keys(session('pending_registration.credentials')));
        $this->assertTrue(Hash::check('Str0ngPass!123', session('pending_registration.credentials.password')));
        $this->assertDatabaseMissing('users', ['email' => 'ofw@example.test']);
        $this->assertGuest();
        Mail::assertNothingQueued();
    }

    public function test_review_displays_only_pending_email(): void
    {
        $this->post(route('register.store'), $this->credentials());
        $this->get(route('register.review'))->assertInertia(fn ($page) => $page
            ->component('Auth/RegisterReview')->where('pending', ['email' => 'ofw@example.test'])->missing('pendingName'));
    }

    public function test_verified_registration_creates_only_an_account_and_consumes_code(): void
    {
        $this->post(route('register.store'), $this->credentials());
        $code = app(OtpService::class)->generate('ofw@example.test', 'register');
        $this->post(route('register.review.store'), ['otp' => $code])->assertRedirect(route('ofw.intake'));
        $user = User::where('email', 'ofw@example.test')->firstOrFail();
        $this->assertSame(UserRole::OFW->value, $user->role);
        $this->assertSame('OFW', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->profile_completed_at);
        $this->assertFalse(Schema::hasTable('clients'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('pending_registration'));
        $this->assertFalse(app(OtpService::class)->verify('ofw@example.test', 'register', $code));
    }

    public function test_wrong_and_expired_codes_never_create_account(): void
    {
        $this->post(route('register.store'), $this->credentials());
        $service = app(OtpService::class);
        $code = $service->generate('ofw@example.test', 'register');
        $wrong = $code === '000000' ? '111111' : '000000';
        $this->post(route('register.review.store'), ['otp' => $wrong])->assertSessionHasErrors('otp');
        $this->travel($service->ttlSeconds() + 1)->seconds();
        $this->post(route('register.review.store'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertDatabaseMissing('users', ['email' => 'ofw@example.test']);
        $this->assertGuest();
    }

    public function test_duplicate_email_is_rejected_before_otp_consumption(): void
    {
        User::factory()->create(['email' => 'ofw@example.test']);
        $this->post(route('register.store'), $this->credentials())->assertRedirect(route('register.review'));
        $code = app(OtpService::class)->generate('ofw@example.test', 'register');
        $this->post(route('register.review.store'), ['otp' => $code])->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'ofw@example.test')->count());
        $this->assertTrue(Cache::has('otp:register:ofw@example.test'));
    }

    public function test_public_profile_and_representative_payloads_are_rejected_at_both_steps(): void
    {
        $this->post(route('register.store'), $this->credentials() + ['type' => 'NEXT_OF_KIN'])
            ->assertSessionHasErrors('type');
        $this->assertNull(session('pending_registration'));
        $this->post(route('register.store'), $this->credentials());
        $code = app(OtpService::class)->generate('ofw@example.test', 'register');
        $this->post(route('register.review.store'), ['otp' => $code, 'ofw_client_id' => 'forged'])
            ->assertSessionHasErrors('ofw_client_id');
        $this->assertDatabaseMissing('users', ['email' => 'ofw@example.test']);
    }

    public function test_pre_cutover_pending_profile_requires_fresh_registration(): void
    {
        $this->withSession(['pending_registration' => [
            'credentials' => ['email' => 'ofw@example.test', 'password' => Hash::make('Str0ngPass!123')],
            'profile' => ['type' => 'NEXT_OF_KIN'],
        ]])->post(route('register.review.store'), ['otp' => '123456'])->assertRedirect(route('register'));
        $this->assertNull(session('pending_registration'));
    }

    public function test_review_sends_code_and_resend_budget_is_enforced(): void
    {
        $this->post(route('register.store'), $this->credentials());
        $this->post(route('register.review.send-otp'))->assertSessionHasNoErrors();
        Mail::assertQueued(OtpMail::class);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->post(route('register.review.send-otp'));
        }
        $this->assertTrue($response->status() === 429 || session()->has('errors'));
        $this->assertDatabaseMissing('users', ['email' => 'ofw@example.test']);
    }

    public function test_failed_account_write_does_not_rearm_consumed_code(): void
    {
        $this->post(route('register.store'), $this->credentials());
        $code = app(OtpService::class)->generate('ofw@example.test', 'register');
        $dispatcher = User::getEventDispatcher();
        User::setEventDispatcher(clone $dispatcher);
        User::creating(function () {
            throw new \RuntimeException('Simulated account write failure');
        });

        try {
            $this->post(route('register.review.store'), ['otp' => $code])->assertSessionHasErrors('registration');
        } finally {
            User::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseMissing('users', ['email' => 'ofw@example.test']);
        $this->assertFalse(Cache::has('otp:register:ofw@example.test'));
        $this->assertNotNull(session('pending_registration.credentials'));
        $this->post(route('register.review.store'), ['otp' => $code])->assertSessionHasErrors('otp');
    }

    public function test_review_requires_pending_credentials(): void
    {
        $this->get(route('register.review'))->assertRedirect(route('register'));
        $this->post(route('register.review.send-otp'))->assertRedirect(route('register'));
        $this->post(route('register.review.store'), ['otp' => '123456'])->assertRedirect(route('register'));
    }
}
