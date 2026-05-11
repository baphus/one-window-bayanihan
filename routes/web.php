<?php

use App\Http\Controllers\ProfileController;
use App\Models\Agency;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'agencies' => Agency::where('is_active', true)->get()->toArray(),
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/cases', [\App\Http\Controllers\CaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/create', [\App\Http\Controllers\CaseController::class, 'create'])->name('cases.create');
    Route::post('/cases', [\App\Http\Controllers\CaseController::class, 'store'])->name('cases.store');
    Route::get('/cases/{case}', [\App\Http\Controllers\CaseController::class, 'show'])->name('cases.show');

    Route::get('/referrals', [\App\Http\Controllers\ReferralController::class, 'index'])->name('referrals.index');
    Route::get('/referrals/create', [\App\Http\Controllers\ReferralController::class, 'create'])->name('referrals.create');
    Route::post('/referrals', [\App\Http\Controllers\ReferralController::class, 'store'])->name('referrals.store');
    Route::get('/referrals/{referral}', [\App\Http\Controllers\ReferralController::class, 'show'])->name('referrals.show');
    Route::patch('/referrals/{referral}/status', [\App\Http\Controllers\ReferralController::class, 'updateStatus'])->name('referrals.update-status');
    Route::post('/referrals/{referral}/milestones', [\App\Http\Controllers\ReferralController::class, 'addMilestone'])->name('referrals.milestones.store');

    Route::get('/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics.index');
});

Route::get('/track', [\App\Http\Controllers\TrackController::class, 'index'])->name('track.index');
Route::post('/track/send-otp', [\App\Http\Controllers\TrackController::class, 'sendOtp'])->name('track.send-otp');
Route::post('/track/verify-otp', [\App\Http\Controllers\TrackController::class, 'verifyOtp'])->name('track.verify-otp');
Route::get('/track/case', [\App\Http\Controllers\TrackController::class, 'show'])->name('track.show');

Route::post('/chatbot/message', [\App\Http\Controllers\ChatbotController::class, 'message'])->name('chatbot.message');

require __DIR__.'/auth.php';
