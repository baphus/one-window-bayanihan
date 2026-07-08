<?php

use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\EmailChangeController;
use App\Http\Controllers\LoginOtpController;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('login', function () {
        return Inertia::render('Auth/Login');
    })->name('login');

    Route::post('login', [LoginOtpController::class, 'init'])
        ->middleware(['turnstile', 'throttle:login'])
        ->name('login.init');

    Route::post('login/verify-otp', [LoginOtpController::class, 'verifyOtp'])
        ->middleware('throttle:otp')
        ->name('login.verify-otp');

    Route::post('login/resend-otp', [LoginOtpController::class, 'resendOtp'])
        ->middleware('throttle:otp')
        ->name('login.resend-otp');

    Route::post('login/verify-totp', [LoginOtpController::class, 'verifyTotp'])
        ->middleware('throttle:totp-challenge')
        ->name('login.verify-totp');

    Route::post('login/verify-recovery-code', [LoginOtpController::class, 'verifyRecoveryCode'])
        ->middleware('throttle:recovery-code')
        ->name('login.verify-recovery-code');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    Route::get('forgot-email', function () {
        return Inertia::render('Auth/ForgotEmail');
    })->name('forgot-email');

});

Route::middleware('auth')->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::put('password', [PasswordController::class, 'update'])
        ->name('password.update');

    Route::get('profile/email-change', [EmailChangeController::class, 'init'])->name('profile.email-change.init');
    Route::post('profile/email-change/send-otp', [EmailChangeController::class, 'sendOtp'])
        ->middleware('throttle:otp')
        ->name('profile.email-change.send-otp');
    Route::post('profile/email-change/verify-otp', [EmailChangeController::class, 'verifyOtp'])
        ->middleware('throttle:otp')
        ->name('profile.email-change.verify-otp');

    Route::post('logout', function () {
        $user = auth()->user();

        AuditLog::create([
            'action' => 'LOGOUT',
            'module' => 'auth',
            'entity_id' => $user?->id,
            'description' => null,
            'user_id' => $user?->id,
            'timestamp' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent() ?? '',
            'request_id' => request()->attributes->get('correlation_id') ?? request()->header('X-Request-ID') ?? (string) Str::uuid(),
        ]);

        auth()->guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    })->name('logout');
});
