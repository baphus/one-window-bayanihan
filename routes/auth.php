<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\LoginOtpController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('login', function () {
        return Inertia::render('Auth/Login');
    })->name('login');

    Route::post('login', [LoginOtpController::class, 'init'])
        ->middleware('throttle:login');

    Route::post('login/verify-otp', [LoginOtpController::class, 'verifyOtp'])
        ->middleware('throttle:otp');

    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', function () {
        auth()->guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    })->name('logout');
});
