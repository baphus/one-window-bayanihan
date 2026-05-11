<?php

use App\Http\Controllers\ProfileController;
use App\Models\Agency;
use App\Services\DashboardService;
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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $service = app(DashboardService::class);
        $user = request()->user();

        $data = match ($user->role) {
            'AGENCY' => $service->getAgencyData($user->agcy_id),
            'ADMIN' => $service->getAdminData(),
            default => $service->getCaseManagerData(),
        };

        $data['role'] = $user->role;

        return Inertia::render('Dashboard', $data);
    })->name('dashboard');

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

    Route::get('/clients', [\App\Http\Controllers\ClientController::class, 'index'])->name('clients.index');
    Route::get('/stakeholders', [\App\Http\Controllers\StakeholderController::class, 'index'])->name('stakeholders.index');
    Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/feedbacks', [\App\Http\Controllers\FeedbackController::class, 'index'])->name('feedbacks.index');

    Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
        Route::get('/agencies', [\App\Http\Controllers\AdminAgencyController::class, 'index'])->name('agencies.index');
        Route::post('/agencies', [\App\Http\Controllers\AdminAgencyController::class, 'store'])->name('agencies.store');
        Route::patch('/agencies/{agency}', [\App\Http\Controllers\AdminAgencyController::class, 'update'])->name('agencies.update');
        Route::delete('/agencies/{agency}', [\App\Http\Controllers\AdminAgencyController::class, 'destroy'])->name('agencies.destroy');

        Route::get('/services', [\App\Http\Controllers\AdminServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [\App\Http\Controllers\AdminServiceController::class, 'store'])->name('services.store');
        Route::patch('/services/{service}', [\App\Http\Controllers\AdminServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [\App\Http\Controllers\AdminServiceController::class, 'destroy'])->name('services.destroy');

        Route::get('/users', [\App\Http\Controllers\AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [\App\Http\Controllers\AdminUserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [\App\Http\Controllers\AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [\App\Http\Controllers\AdminUserController::class, 'destroy'])->name('users.destroy');
    });

    Route::get('/system-settings', function () {
        return Inertia::render('SystemSettings/Index');
    })->name('system-settings.index');
});

Route::get('/partners', function () {
    $agencies = Agency::with('services')->where('is_active', true)->get()->toArray();
    return Inertia::render('PublicAgencies/Index', ['agencies' => $agencies]);
})->name('partners');

Route::get('/contact', function () {
    return Inertia::render('Contact/Index');
})->name('contact');

Route::get('/track', [\App\Http\Controllers\TrackController::class, 'index'])->name('track.index');
Route::post('/track/send-otp', [\App\Http\Controllers\TrackController::class, 'sendOtp'])->name('track.send-otp');
Route::post('/track/verify-otp', [\App\Http\Controllers\TrackController::class, 'verifyOtp'])->name('track.verify-otp');
Route::get('/track/case', [\App\Http\Controllers\TrackController::class, 'show'])->name('track.show');

Route::post('/chatbot/message', [\App\Http\Controllers\ChatbotController::class, 'message'])->name('chatbot.message');

require __DIR__.'/auth.php';
