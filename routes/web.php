<?php

use App\Http\Controllers\Admin\ActiveSessionsController;
use App\Http\Controllers\Admin\AdminCaseCategoryController;
use App\Http\Controllers\Admin\AdminCaseIssueController;
use App\Http\Controllers\Admin\AdminCaseStatusController;
use App\Http\Controllers\Admin\DataExportController;
use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\Admin\LogViewerController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\OverdueReferralController;
use App\Http\Controllers\Admin\SecuritySettingsController;
use App\Http\Controllers\AdminAgencyController;
use App\Http\Controllers\AdminServiceController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AgencyServiceController;
use App\Http\Controllers\AgencyServqualConfigController;
use App\Http\Controllers\Api\ClientSelectController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseDocumentController;
use App\Http\Controllers\CaseIssueController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicFeedbackController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\StakeholderController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TrackController;
use App\Models\Agency;
use App\Services\DashboardService;
use App\Services\ReportsService;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public feedback submission — no auth required (token-based)
Route::get('/feedback/{token}', [PublicFeedbackController::class, 'showForm'])
    ->name('feedbacks.submit-page')
    ->middleware('throttle:30,1');
Route::post('/feedback/{token}', [PublicFeedbackController::class, 'submit'])
    ->name('feedbacks.submit')
    ->middleware('throttle:10,1');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'agencies' => Agency::where('is_active', true)->get()->toArray(),
    ]);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        $service = app(DashboardService::class);
        $reportsService = app(ReportsService::class);
        $user = request()->user();

        $data = match ($user->role) {
            'AGENCY' => $service->getAgencyData($user),
            'ADMIN' => $service->getAdminData(),
            default => $service->getCaseManagerData($user),
        };

        $data['role'] = $user->role;
        $data['caseTrends'] = $reportsService->getCaseTrends();
        $data['referralStatusDistribution'] = $reportsService->getReferralStatusDistribution();

        return Inertia::render('Dashboard', $data);
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/profile/mfa/status', [MfaController::class, 'status'])->name('profile.mfa.status');
    Route::post('/profile/mfa/generate', [MfaController::class, 'generateSecret'])->name('profile.mfa.generate');
    Route::post('/profile/mfa/verify', [MfaController::class, 'verifyAndEnable'])->name('profile.mfa.verify');
    Route::post('/profile/mfa/disable', [MfaController::class, 'disable'])->name('profile.mfa.disable');
    Route::get('/profile/mfa/recovery-codes', [MfaController::class, 'getRecoveryCodes'])->name('profile.mfa.recovery-codes');
    Route::post('/profile/mfa/recovery-codes/regenerate', [MfaController::class, 'regenerateRecoveryCodes'])->name('profile.mfa.recovery-codes.regenerate');

    // All-roles routes: referrals, reports, notifications
    Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
    Route::get('/referrals/create', [ReferralController::class, 'create'])->name('referrals.create');
    Route::post('/referrals', [ReferralController::class, 'store'])->name('referrals.store');
    Route::get('/referrals/export-excel', [ReferralController::class, 'exportExcel'])->name('referrals.export-excel');
    Route::get('/referrals/{referral}', [ReferralController::class, 'show'])->name('referrals.show');
    Route::patch('/referrals/{referral}/status', [ReferralController::class, 'updateStatus'])->name('referrals.update-status');
    Route::post('/referrals/{referral}/milestones', [ReferralController::class, 'addMilestone'])->name('referrals.milestones.store');

    Route::post('/referrals/{referral}/comments', [ReferralController::class, 'addComment'])->name('referrals.comments.store');
    Route::post('/referrals/{referral}/comments/{comment}/reply', [ReferralController::class, 'replyToComment'])->name('referrals.comments.reply');
    Route::post('/referrals/{referral}/attachments', [ReferralController::class, 'addAttachment'])->name('referrals.attachments.store');
    Route::post('/referrals/{referral}/attachments/{attachment}/replace', [ReferralController::class, 'replaceAttachment'])->name('referrals.attachments.replace');
    Route::get('/referrals/{referral}/attachments/{attachment}/download', [ReferralController::class, 'downloadAttachment'])->name('referrals.attachments.download');
    Route::get('/referrals/{referral}/attachments/{versionGroupId}/versions', [ReferralController::class, 'getAttachmentVersions'])->name('referrals.attachments.versions');
    Route::post('/referrals/{referral}/compliance/{compliance}/fulfill', [ReferralController::class, 'fulfillCompliance'])->name('referrals.compliance.fulfill');

    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index')->middleware('throttle:60,1');
    Route::post('/reports/ai-insight', [ReportsController::class, 'aiInsight'])->name('reports.ai-insight')->middleware('throttle:10,1');
    Route::get('/reports/export-pdf', [ReportsController::class, 'exportPdf'])->name('reports.export-pdf');
    Route::get('/reports/export-excel', [ReportsController::class, 'exportExcel'])->name('reports.export-excel');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::patch('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::get('/notifications/page', function () {
        return Inertia::render('Notifications/Index');
    })->name('notifications.page');

    // Role-gated: CASE_MANAGER + ADMIN only
    Route::middleware('role:CASE_MANAGER,ADMIN')->group(function () {
        Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
        Route::get('/cases/create', [CaseController::class, 'create'])->name('cases.create');
        Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
        Route::get('/cases/drafts', [CaseController::class, 'drafts'])->name('cases.drafts');
        Route::get('/cases/export-excel', [CaseController::class, 'exportExcel'])->name('cases.export-excel');
        Route::delete('/cases/{case}/destroy-draft', [CaseController::class, 'destroyDraft'])->name('cases.drafts.destroy');
        Route::get('/cases/{case}/edit-draft', [CaseController::class, 'editDraft'])->name('cases.edit-draft');
        Route::put('/cases/{case}/save-draft', [CaseController::class, 'updateDraft'])->name('cases.save-draft');
        Route::post('/cases/{case}/publish', [CaseController::class, 'publish'])->name('cases.publish');
        Route::post('/cases/{case}/archive', [CaseController::class, 'archive'])->name('cases.archive');
        Route::post('/cases/{case}/unarchive', [CaseController::class, 'unarchive'])->name('cases.unarchive');
        Route::patch('/cases/{case}', [CaseController::class, 'update'])->name('cases.update');
        Route::post('/cases/{case}/toggle-status', [CaseController::class, 'toggleStatus'])->name('cases.toggle-status');

        Route::post('/case-issues/quick', [CaseIssueController::class, 'quickStore'])->name('case-issues.quick');

        Route::get('/stakeholders', [StakeholderController::class, 'index'])->name('stakeholders.index');
        Route::get('/stakeholders/{stakeholder}', [StakeholderController::class, 'show'])->name('stakeholders.show');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    // Feedback views: CASE_MANAGER, ADMIN, and AGENCY (controller scopes by agency_id)
    Route::middleware('role:CASE_MANAGER,ADMIN,AGENCY')->group(function () {
        Route::get('/feedbacks', [FeedbackController::class, 'index'])->name('feedbacks.index');
        Route::get('/feedbacks/servqual-config', [FeedbackController::class, 'servqualConfig'])->name('feedbacks.servqual-config');
        Route::get('/feedbacks/export-excel', [FeedbackController::class, 'exportExcel'])->name('feedbacks.export-excel');
        Route::get('/feedbacks/{feedback}', [FeedbackController::class, 'show'])->name('feedbacks.show');
    });

    // Case show: AGENCY can view cases with active referrals (authorized in controller)
    Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show')
        ->middleware('role:CASE_MANAGER,ADMIN,AGENCY');

    Route::middleware('role:CASE_MANAGER,ADMIN,AGENCY')->group(function () {
        Route::get('/cases/{case}/documents', [CaseDocumentController::class, 'index'])->name('cases.documents.index');
        Route::get('/cases/{case}/documents/{document}', [CaseDocumentController::class, 'show'])->name('cases.documents.show');
        Route::get('/cases/{case}/documents/{document}/download', [CaseDocumentController::class, 'download'])->name('cases.documents.download');
    });

    Route::middleware('role:CASE_MANAGER')->group(function () {
        Route::post('/cases/{case}/documents', [CaseDocumentController::class, 'store'])->name('cases.documents.store');
        Route::delete('/cases/{case}/documents/{document}', [CaseDocumentController::class, 'destroy'])->name('cases.documents.destroy');
    });

    // Client routes: accessible to CASE_MANAGER, ADMIN, and AGENCY (controller handles per-role authorization)
    Route::middleware('role:CASE_MANAGER,ADMIN,AGENCY')->group(function () {
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/export-excel', [ClientController::class, 'exportExcel'])->name('clients.export-excel');
        Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::post('/clients/{client}/avatar', [ClientController::class, 'storeAvatar'])->name('clients.avatar.store');
        Route::delete('/clients/{client}/avatar', [ClientController::class, 'destroyAvatar'])->name('clients.avatar.destroy');
    });

    Route::middleware('role:AGENCY')->group(function () {
        Route::get('/services', [AgencyServiceController::class, 'index'])->name('agency.services.index');
        Route::post('/services', [AgencyServiceController::class, 'store'])->name('agency.services.store');
        Route::patch('/services/{service}', [AgencyServiceController::class, 'update'])->name('agency.services.update');
        Route::delete('/services/{service}', [AgencyServiceController::class, 'destroy'])->name('agency.services.destroy');
    });

    Route::middleware('role:AGENCY')->prefix('servqual-configs')->name('servqual-configs.')->group(function () {
        Route::get('/', [AgencyServqualConfigController::class, 'index'])->name('index');
        Route::get('/create', [AgencyServqualConfigController::class, 'create'])->name('create');
        Route::post('/', [AgencyServqualConfigController::class, 'store'])->name('store');
        Route::get('/{config}/edit', [AgencyServqualConfigController::class, 'edit'])->name('edit');
        Route::patch('/{config}', [AgencyServqualConfigController::class, 'update'])->name('update');
        Route::patch('/{config}/activate', [AgencyServqualConfigController::class, 'activate'])->name('activate');
        Route::delete('/{config}', [AgencyServqualConfigController::class, 'destroy'])->name('destroy');
    });

    // Agency detail — accessible by ADMIN and CASE_MANAGER (read-only for non-ADMIN)
    Route::middleware(['role:ADMIN,CASE_MANAGER', 'ip.whitelist'])
        ->prefix('admin')->name('admin.')
        ->get('/agencies/{agency}', [AdminAgencyController::class, 'show'])
        ->name('agencies.show');

    // Onboarding routes
    Route::get('/onboarding/state', [OnboardingController::class, 'state'])->name('onboarding.state');
    Route::post('/onboarding/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::post('/onboarding/replay', [OnboardingController::class, 'replay'])->name('onboarding.replay');
    Route::post('/onboarding/step', [OnboardingController::class, 'updateStep'])->name('onboarding.step');
    Route::post('/onboarding/skip-profile', [OnboardingController::class, 'skipProfile'])->name('onboarding.skip-profile');

    Route::prefix('admin')->name('admin.')->middleware(['role:ADMIN', 'ip.whitelist'])->group(function () {
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
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::patch('/users/{user}/verify', [AdminUserController::class, 'verify'])->name('users.verify');
        Route::post('/users/{user}/email-change/send-otp', [AdminUserController::class, 'sendEmailChangeOtp'])->name('users.email-change.send-otp');
        Route::post('/users/{user}/email-change/verify-otp', [AdminUserController::class, 'verifyEmailChangeOtp'])->name('users.email-change.verify-otp');

        Route::get('/system-settings', [SystemSettingsController::class, 'index'])->name('system-settings.index');
        Route::post('/system-settings', [SystemSettingsController::class, 'update'])->name('system-settings.update');

        Route::get('/case-categories', [AdminCaseCategoryController::class, 'index'])->name('case-categories.index');
        Route::post('/case-categories', [AdminCaseCategoryController::class, 'store'])->name('case-categories.store');
        Route::patch('/case-categories/{caseCategory}', [AdminCaseCategoryController::class, 'update'])->name('case-categories.update');
        Route::delete('/case-categories/{caseCategory}', [AdminCaseCategoryController::class, 'destroy'])->name('case-categories.destroy');

        Route::get('/case-statuses', [AdminCaseStatusController::class, 'index'])->name('case-statuses.index');
        Route::post('/case-statuses', [AdminCaseStatusController::class, 'store'])->name('case-statuses.store');
        Route::patch('/case-statuses/{caseStatus}', [AdminCaseStatusController::class, 'update'])->name('case-statuses.update');
        Route::delete('/case-statuses/{caseStatus}', [AdminCaseStatusController::class, 'destroy'])->name('case-statuses.destroy');

        Route::get('/case-issues', [AdminCaseIssueController::class, 'index'])->name('case-issues.index');
        Route::post('/case-issues', [AdminCaseIssueController::class, 'store'])->name('case-issues.store');
        Route::patch('/case-issues/{caseIssue}', [AdminCaseIssueController::class, 'update'])->name('case-issues.update');
        Route::delete('/case-issues/{caseIssue}', [AdminCaseIssueController::class, 'destroy'])->name('case-issues.destroy');

        Route::get('/data-export', [DataExportController::class, 'index'])->name('data-export.index');
        Route::get('/data-export/export', [DataExportController::class, 'export'])->name('data-export.export');

        Route::prefix('system')->name('system.')->group(function () {
            Route::get('/logs', [LogViewerController::class, 'index'])->name('logs');
            Route::get('/logs/entries', [LogViewerController::class, 'entries'])->name('logs.entries');
            Route::get('/logs/download', [LogViewerController::class, 'download'])->name('logs.download');

            Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance');
            Route::post('/maintenance/toggle', [MaintenanceController::class, 'toggle'])->name('maintenance.toggle');

            Route::get('/security', [SecuritySettingsController::class, 'index'])->name('security');
            Route::post('/security', [SecuritySettingsController::class, 'update'])->name('security.update');

            Route::get('/active-sessions', [ActiveSessionsController::class, 'index'])->name('active-sessions');
            Route::post('/active-sessions/{session}/terminate', [ActiveSessionsController::class, 'terminate'])->name('active-sessions.terminate');

            Route::get('/email-logs', [EmailLogController::class, 'index'])->name('email-logs.index');
            Route::post('/email-logs/{emailLog}/resend', [EmailLogController::class, 'resend'])->name('email-logs.resend');

        });
    });
});

Route::middleware(['auth', 'verified', 'role:ADMIN,CASE_MANAGER,AGENCY'])->group(function () {
    Route::get('/overdue-referrals', [OverdueReferralController::class, 'index'])->name('overdue-referrals.index');
    Route::post('/overdue-referrals/send-reminders', [OverdueReferralController::class, 'sendReminders'])->name('overdue-referrals.send-reminders');
});

Route::get('/partners', function () {
    $agencies = Agency::with('services')->where('is_active', true)->get()->toArray();

    return Inertia::render('PublicAgencies/Index', ['agencies' => $agencies]);
})->name('partners');

Route::get('/partners/{agency:slug}', function (Agency $agency) {
    abort_unless($agency->is_active, 404);

    $agency->load(['services' => fn ($q) => $q->with('requirements')->orderBy('name')]);

    return Inertia::render('PublicAgencies/Show', [
        'agency' => $agency->toArray(),
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('partners.show');

Route::get('/contact', function () {
    return Inertia::render('Contact/Index');
})->name('contact');

Route::get('/privacy', function () {
    return Inertia::render('Legal/PrivacyPolicy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Legal/TermsOfService');
})->name('terms');

Route::get('/track', [TrackController::class, 'index'])->name('track.index');
Route::post('/track/send-otp', [TrackController::class, 'sendOtp'])
    ->name('track.send-otp')
    ->middleware('throttle:tracking');
Route::get('/track/verify-otp', function () {
    return redirect()->route('track.index');
})->name('track.verify-otp.get');
Route::post('/track/verify-otp', [TrackController::class, 'verifyOtp'])
    ->name('track.verify-otp')
    ->middleware('throttle:tracking');
Route::get('/track/case', [TrackController::class, 'show'])->name('track.show');

Route::prefix('helpdesk')->name('helpdesk.')->group(function () {
    Route::get('/', function (Request $request) {
        return inertia('Helpdesk/Index', [
            'category' => $request->query('category'),
        ]);
    })->name('index');
    Route::get('/search', function (Request $request) {
        return inertia('Helpdesk/Search', [
            'query' => $request->query('q', ''),
        ]);
    })->name('search');
    Route::get('/{slug}', fn ($slug) => inertia('Helpdesk/Show', [
        'slug' => $slug,
    ]))->name('show');
});

// API routes (authenticated via web session) — in web.php for session middleware support
Route::middleware(['auth', 'throttle:api-global'])->prefix('api')->group(function () {
    // Client selection for case creation form
    Route::get('/clients', [ClientSelectController::class, 'search']);
    Route::get('/clients/{client}', [ClientSelectController::class, 'show']);

});

Route::post('/chatbot/message', [ChatbotController::class, 'message'])
    ->name('chatbot.message')
    ->middleware('throttle:30,1');

require __DIR__.'/auth.php';
