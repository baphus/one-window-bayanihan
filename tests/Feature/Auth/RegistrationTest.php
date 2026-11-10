<?php

namespace Tests\Feature\Auth;

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
        ])->assertRedirect('/register/review');
        $code = app(OtpService::class)->generate('test@example.com', 'register');
        $this->post('/register/review', [
            'otp' => $code,
            'profile_picture' => UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg'),
        ])->assertSessionHasErrors('profile_picture');
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
        $this->assertTrue(app(OtpService::class)->verify('test@example.com', 'register', $code));
    }
}
