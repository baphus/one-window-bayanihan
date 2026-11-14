<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_photo_capture_is_rejected_without_consuming_otp(): void
    {
        Mail::fake();
        $this->post('/register', [
            'email' => 'test@example.com',
            'password' => 'P@ssw0rd!',
            'password_confirmation' => 'P@ssw0rd!',
            'first_name' => 'Maria', 'last_name' => 'Cruz',
            'date_of_birth' => '1990-01-01', 'sex' => 'FEMALE',
        ])->assertRedirect('/register/review');
        $code = app(OtpService::class)->generate('test@example.com', 'register');
        $this->post('/register/review', [
            'otp' => $code,
            'profile_picture' => UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg'),
        ])->assertSessionHasErrors('profile_picture');
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
        $this->assertTrue(app(OtpService::class)->verify('test@example.com', 'register', $code));
    }

    public function test_registration_requires_identity_before_otp_and_creates_account_only_after_verification(): void
    {
        Mail::fake();
        $details = [
            'email' => 'maria@example.test', 'password' => 'P@ssw0rd!',
            'password_confirmation' => 'P@ssw0rd!',
            'first_name' => 'Maria', 'middle_name' => 'Santos', 'last_name' => 'Cruz',
            'suffix' => 'Jr.', 'date_of_birth' => '1990-01-01', 'sex' => 'FEMALE',
        ];
        $this->post('/register', array_diff_key($details, array_flip(['first_name', 'last_name', 'date_of_birth', 'sex'])))
            ->assertSessionHasErrors(['first_name', 'last_name', 'date_of_birth', 'sex']);
        $this->post('/register', $details)->assertRedirect('/register/review');
        $this->assertDatabaseMissing('users', ['email' => $details['email']]);
        $code = app(OtpService::class)->generate($details['email'], 'register');
        $this->post('/register/review', ['otp' => $code])->assertSessionHasNoErrors();
        $user = User::where('email', $details['email'])->firstOrFail();
        $this->assertSame('Maria Santos Cruz, Jr.', $user->name);
        $this->assertSame('1990-01-01', $user->date_of_birth->toDateString());
        $this->assertSame('FEMALE', $user->sex);
        $this->assertNull($user->avatar_url);
    }
}
