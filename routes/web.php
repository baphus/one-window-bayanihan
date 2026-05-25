<?php

use App\Http\Controllers\Admin\AdminCaseStatusController;
use App\Http\Controllers\Admin\HelpdeskArticleController;
use App\Http\Controllers\Admin\HelpdeskCategoryController;
use App\Http\Controllers\Admin\HelpdeskTagController;
use App\Http\Controllers\Admin\OverdueReferralController;
use App\Http\Controllers\AdminAgencyController;
use App\Http\Controllers\AdminServiceController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HelpdeskController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\StakeholderController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TrackController;
use App\Models\Agency;
use App\Services\AnalyticsService;
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
        $analytics = app(AnalyticsService::class);
        $user = request()->user();

        $data = match ($user->role) {
            'AGENCY' => $service->getAgencyData($user->agcy_id),
            'ADMIN' => $service->getAdminData(),
            default => $service->getCaseManagerData(),
        };

        $data['role'] = $user->role;
        $data['caseTrends'] = $analytics->getCaseTrends();
        $data['referralStatusDistribution'] = $analytics->getReferralStatusDistribution();
        $data['caseTypeDistribution'] = $analytics->getCaseTypeDistribution();

        return Inertia::render('Dashboard', $data);
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/create', [CaseController::class, 'create'])->name('cases.create');
    Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
    Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show');
    Route::patch('/cases/{case}', [CaseController::class, 'update'])->name('cases.update');
    Route::post('/cases/{case}/toggle-status', [CaseController::class, 'toggleStatus'])->name('cases.toggle-status');

    Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
    Route::get('/referrals/create', [ReferralController::class, 'create'])->name('referrals.create');
    Route::post('/referrals', [ReferralController::class, 'store'])->name('referrals.store');
    Route::get('/referrals/{referral}', [ReferralController::class, 'show'])->name('referrals.show');
    Route::patch('/referrals/{referral}/status', [ReferralController::class, 'updateStatus'])->name('referrals.update-status');
    Route::post('/referrals/{referral}/milestones', [ReferralController::class, 'addMilestone'])->name('referrals.milestones.store');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');

    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('/stakeholders', [StakeholderController::class, 'index'])->name('stakeholders.index');
    Route::get('/stakeholders/{stakeholder}', [StakeholderController::class, 'show'])->name('stakeholders.show');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/feedbacks', [FeedbackController::class, 'index'])->name('feedbacks.index');

    Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
        Route::get('/agencies', [AdminAgencyController::class, 'index'])->name('agencies.index');
        Route::post('/agencies', [AdminAgencyController::class, 'store'])->name('agencies.store');
        Route::patch('/agencies/{agency}', [AdminAgencyController::class, 'update'])->name('agencies.update');
        Route::delete('/agencies/{agency}', [AdminAgencyController::class, 'destroy'])->name('agencies.destroy');

        Route::get('/services', [AdminServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [AdminServiceController::class, 'store'])->name('services.store');
        Route::patch('/services/{service}', [AdminServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [AdminServiceController::class, 'destroy'])->name('services.destroy');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('/system-settings', [SystemSettingsController::class, 'index'])->name('system-settings.index');
        Route::post('/system-settings', [SystemSettingsController::class, 'update'])->name('system-settings.update');

        Route::get('/helpdesk/articles', [HelpdeskArticleController::class, 'index'])->name('helpdesk.articles.index');
        Route::get('/helpdesk/articles/create', [HelpdeskArticleController::class, 'create'])->name('helpdesk.articles.create');
        Route::post('/helpdesk/articles', [HelpdeskArticleController::class, 'store'])->name('helpdesk.articles.store');
        Route::get('/helpdesk/articles/{article}/edit', [HelpdeskArticleController::class, 'edit'])->name('helpdesk.articles.edit');
        Route::patch('/helpdesk/articles/{article}', [HelpdeskArticleController::class, 'update'])->name('helpdesk.articles.update');
        Route::delete('/helpdesk/articles/{article}', [HelpdeskArticleController::class, 'destroy'])->name('helpdesk.articles.destroy');
        Route::post('/helpdesk/articles/{article}/restore', [HelpdeskArticleController::class, 'restore'])->name('helpdesk.articles.restore');
        Route::post('/helpdesk/articles/{article}/toggle-featured', [HelpdeskArticleController::class, 'toggleFeatured'])->name('helpdesk.articles.toggle-featured');
        Route::get('/helpdesk/articles/{article}/versions', [HelpdeskArticleController::class, 'versions'])->name('helpdesk.articles.versions');

        Route::get('/helpdesk/categories', [HelpdeskCategoryController::class, 'index'])->name('helpdesk.categories.index');
        Route::post('/helpdesk/categories', [HelpdeskCategoryController::class, 'store'])->name('helpdesk.categories.store');
        Route::patch('/helpdesk/categories/{category}', [HelpdeskCategoryController::class, 'update'])->name('helpdesk.categories.update');
        Route::delete('/helpdesk/categories/{category}', [HelpdeskCategoryController::class, 'destroy'])->name('helpdesk.categories.destroy');

        Route::get('/helpdesk/tags', [HelpdeskTagController::class, 'index'])->name('helpdesk.tags.index');
        Route::post('/helpdesk/tags', [HelpdeskTagController::class, 'store'])->name('helpdesk.tags.store');
        Route::patch('/helpdesk/tags/{tag}', [HelpdeskTagController::class, 'update'])->name('helpdesk.tags.update');
        Route::delete('/helpdesk/tags/{tag}', [HelpdeskTagController::class, 'destroy'])->name('helpdesk.tags.destroy');

        Route::post('/helpdesk/articles/upload-image', [HelpdeskArticleController::class, 'uploadImage'])->name('helpdesk.articles.upload-image');

        Route::get('/case-statuses', [AdminCaseStatusController::class, 'index'])->name('case-statuses.index');
        Route::post('/case-statuses', [AdminCaseStatusController::class, 'store'])->name('case-statuses.store');
        Route::patch('/case-statuses/{caseStatus}', [AdminCaseStatusController::class, 'update'])->name('case-statuses.update');
        Route::delete('/case-statuses/{caseStatus}', [AdminCaseStatusController::class, 'destroy'])->name('case-statuses.destroy');

    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/overdue-referrals', [OverdueReferralController::class, 'index'])->name('overdue-referrals.index');
    Route::post('/overdue-referrals/send-reminders', [OverdueReferralController::class, 'sendReminders'])->name('overdue-referrals.send-reminders');
});

Route::get('/partners', function () {
    $agencies = Agency::with('services')->where('is_active', true)->get()->toArray();

    return Inertia::render('PublicAgencies/Index', ['agencies' => $agencies]);
})->name('partners');

Route::get('/contact', function () {
    return Inertia::render('Contact/Index');
})->name('contact');

Route::get('/track', [TrackController::class, 'index'])->name('track.index');
Route::post('/track/send-otp', [TrackController::class, 'sendOtp'])->name('track.send-otp');
Route::post('/track/verify-otp', [TrackController::class, 'verifyOtp'])->name('track.verify-otp');
Route::get('/track/case', [TrackController::class, 'show'])->name('track.show');

Route::prefix('helpdesk')->name('helpdesk.')->group(function () {
    Route::get('/', [HelpdeskController::class, 'index'])->name('index');
    Route::get('/search', [HelpdeskController::class, 'search'])->name('search');
    Route::get('/{slug}', [HelpdeskController::class, 'show'])->name('show');
    Route::post('/feedback', [HelpdeskController::class, 'feedback'])->name('feedback');
});

Route::post('/chatbot/message', [ChatbotController::class, 'message'])->name('chatbot.message');

require __DIR__.'/auth.php';
