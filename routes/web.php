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
use App\Http\Controllers\Api\ClientSelectController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseDocumentController;
use App\Http\Controllers\CaseIssueController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSurveyController;
use App\Http\Controllers\ReferralClientRequestController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\StakeholderController;
use App\Http\Controllers\SurveyFormController;
use App\Http\Controllers\SurveyResponseController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TrackController;
use App\Models\Agency;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

// Public survey submission — no auth required (token-based)
Route::get('/survey/{token}', [PublicSurveyController::class, 'show'])
    ->name('survey.public.show')
    ->middleware('throttle:30,1');
Route::post('/survey/{token}', [PublicSurveyController::class, 'submit'])
    ->name('survey.public.submit')
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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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
    Route::post('/referrals/{referral}/attachments/{attachment}/remove', [ReferralController::class, 'deleteAttachment'])->name('referrals.attachments.delete');
    Route::get('/referrals/{referral}/attachments/{attachment}/download', [ReferralController::class, 'downloadAttachment'])->name('referrals.attachments.download');
    Route::get('/referrals/{referral}/attachments/{versionGroupId}/versions', [ReferralController::class, 'getAttachmentVersions'])->name('referrals.attachments.versions');

    Route::get('/api/referrals/{referral}/audit-logs', [AuditLogController::class, 'referralAuditLogs'])->name('api.referrals.audit-logs');

    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index')->middleware('throttle:120,1');
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
        Route::get('/cases/{case}/export-pdf', [CaseController::class, 'exportPdf'])->name('cases.export-pdf');
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
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');

        Route::get('/api/cases/{case}/audit-logs', [AuditLogController::class, 'caseAuditLogs'])->name('api.cases.audit-logs');
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
        Route::post('/referrals/{referral}/client-requests', [ReferralClientRequestController::class, 'store'])->name('referrals.client-requests.store')->middleware(['throttle:agency-client-request-create', 'throttle:agency-client-delivery-recipient']);
        Route::post('/client-requests/{clientRequest}/messages', [ReferralClientRequestController::class, 'sendMessage'])->name('referrals.client-requests.messages.store');
        Route::post('/client-requests/{clientRequest}/complete', [ReferralClientRequestController::class, 'complete'])->name('referrals.client-requests.complete');
        Route::post('/client-requests/{clientRequest}/cancel', [ReferralClientRequestController::class, 'cancel'])->name('referrals.client-requests.cancel');
        Route::post('/client-requests/{clientRequest}/reopen', [ReferralClientRequestController::class, 'reopen'])->name('referrals.client-requests.reopen')->middleware(['throttle:agency-client-access', 'throttle:agency-client-delivery-recipient']);
        Route::post('/client-requests/{clientRequest}/access/issue', [ReferralClientRequestController::class, 'issue'])->name('referrals.client-requests.access.issue')->middleware(['throttle:agency-client-access', 'throttle:agency-client-delivery-recipient']);
        Route::post('/client-requests/{clientRequest}/access/reissue', [ReferralClientRequestController::class, 'reissue'])->name('referrals.client-requests.access.reissue')->middleware(['throttle:agency-client-access', 'throttle:agency-client-delivery-recipient']);

        Route::get('/services', [AgencyServiceController::class, 'index'])->name('agency.services.index');
        Route::post('/services', [AgencyServiceController::class, 'store'])->name('agency.services.store');
        Route::patch('/services/{service}', [AgencyServiceController::class, 'update'])->name('agency.services.update');
        Route::delete('/services/{service}', [AgencyServiceController::class, 'destroy'])->name('agency.services.destroy');
    });

    Route::middleware('role:CASE_MANAGER,ADMIN,AGENCY')->group(function () {
        Route::get('/referrals/{referral}/client-requests', [ReferralClientRequestController::class, 'index'])->name('referrals.client-requests.index');
        Route::post('/client-access-links/{accessLink}/revoke', [ReferralClientRequestController::class, 'revoke'])->name('referrals.client-requests.access.revoke');
    });

    // Survey form builder (AGENCY only)
    Route::middleware('role:AGENCY')->prefix('survey-forms')->name('survey.forms.')->group(function () {
        Route::get('/', [SurveyFormController::class, 'index'])->name('index');
        Route::get('/create', [SurveyFormController::class, 'create'])->name('create');
        Route::post('/', [SurveyFormController::class, 'store'])->name('store');
        Route::get('/{form}/edit', [SurveyFormController::class, 'edit'])->name('edit');
        Route::patch('/{form}', [SurveyFormController::class, 'update'])->name('update');
        Route::patch('/{form}/activate', [SurveyFormController::class, 'activate'])->name('activate');
        Route::delete('/{form}', [SurveyFormController::class, 'destroy'])->name('destroy');
    });

    // Survey responses dashboard (AGENCY, CASE_MANAGER, ADMIN)
    Route::middleware('role:CASE_MANAGER,ADMIN,AGENCY')->prefix('surveys')->name('survey.responses.')->group(function () {
        Route::get('/', [SurveyResponseController::class, 'index'])->name('index');
        Route::get('/{invitation}', [SurveyResponseController::class, 'show'])->name('show');
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
    Route::post('/onboarding/guide-seen', [OnboardingController::class, 'markGuideSeen'])->name('onboarding.guide-seen');
    Route::post('/onboarding/checklist/mark', [OnboardingController::class, 'markChecklistItem'])->name('onboarding.checklist.mark');
    Route::post('/onboarding/checklist/dismiss', [OnboardingController::class, 'dismissChecklist'])->name('onboarding.checklist.dismiss');
    Route::post('/onboarding/skip-profile', [OnboardingController::class, 'skipProfile'])->name('onboarding.skip-profile');

    Route::prefix('admin')->name('admin.')->middleware(['role:ADMIN', 'ip.whitelist'])->group(function () {
        Route::get('/agencies', [AdminAgencyController::class, 'index'])->name('agencies.index');
        Route::post('/agencies', [AdminAgencyController::class, 'store'])->name('agencies.store');
        Route::patch('/agencies/{agency}', [AdminAgencyController::class, 'update'])->name('agencies.update');
        Route::delete('/agencies/{agency}', [AdminAgencyController::class, 'destroy'])->name('agencies.destroy');
        Route::patch('/agencies/{agency}/reactivate', [AdminAgencyController::class, 'reactivate'])->name('agencies.reactivate');

        Route::get('/services', [AdminServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [AdminServiceController::class, 'store'])->name('services.store');
        Route::patch('/services/{service}', [AdminServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [AdminServiceController::class, 'destroy'])->name('services.destroy');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users/invite', [AdminUserController::class, 'invite'])->name('users.invite');
        Route::post('/users/invites/{inviteId}/resend', [AdminUserController::class, 'resendInvite'])->name('users.invites.resend');
        Route::delete('/users/invites/{inviteId}', [AdminUserController::class, 'cancelInvite'])->name('users.invites.cancel');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::patch('/users/{user}/reactivate', [AdminUserController::class, 'reactivate'])->name('users.reactivate');
        Route::patch('/users/{user}/verify', [AdminUserController::class, 'verify'])->name('users.verify');
        Route::post('/users/{user}/email-change/send-otp', [AdminUserController::class, 'sendEmailChangeOtp'])->name('users.email-change.send-otp');
        Route::post('/users/{user}/email-change/verify-otp', [AdminUserController::class, 'verifyEmailChangeOtp'])->name('users.email-change.verify-otp');

        Route::get('/system-settings', [SystemSettingsController::class, 'index'])->name('system-settings.index');
        Route::post('/system-settings', [SystemSettingsController::class, 'update'])->name('system-settings.update');

        Route::get('/case-categories', [AdminCaseCategoryController::class, 'index'])->name('case-categories.index');
        Route::post('/case-categories', [AdminCaseCategoryController::class, 'store'])->name('case-categories.store');
        Route::patch('/case-categories/{caseCategory}', [AdminCaseCategoryController::class, 'update'])->name('case-categories.update');
        Route::delete('/case-categories/{caseCategory}', [AdminCaseCategoryController::class, 'destroy'])->name('case-categories.destroy');
        Route::patch('/case-categories/{caseCategory}/reactivate', [AdminCaseCategoryController::class, 'reactivate'])->name('case-categories.reactivate');

        Route::get('/case-statuses', [AdminCaseStatusController::class, 'index'])->name('case-statuses.index');
        Route::post('/case-statuses', [AdminCaseStatusController::class, 'store'])->name('case-statuses.store');
        Route::patch('/case-statuses/{caseStatus}', [AdminCaseStatusController::class, 'update'])->name('case-statuses.update');
        Route::delete('/case-statuses/{caseStatus}', [AdminCaseStatusController::class, 'destroy'])->name('case-statuses.destroy');

        Route::get('/case-issues', [AdminCaseIssueController::class, 'index'])->name('case-issues.index');
        Route::post('/case-issues', [AdminCaseIssueController::class, 'store'])->name('case-issues.store');
        Route::patch('/case-issues/{caseIssue}', [AdminCaseIssueController::class, 'update'])->name('case-issues.update');
        Route::delete('/case-issues/{caseIssue}', [AdminCaseIssueController::class, 'destroy'])->name('case-issues.destroy');
        Route::patch('/case-issues/{caseIssue}/reactivate', [AdminCaseIssueController::class, 'reactivate'])->name('case-issues.reactivate');

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

Route::middleware(['auth', 'role:ADMIN,CASE_MANAGER,AGENCY'])->group(function () {
    Route::get('/overdue-referrals', [OverdueReferralController::class, 'index'])->name('overdue-referrals.index');
    Route::post('/overdue-referrals/send-reminders', [OverdueReferralController::class, 'sendReminders'])->name('overdue-referrals.send-reminders');
});

Route::get('/partners', function () {
    $agencies = Agency::with('services')->where('is_active', true)->get()->toArray();

    return Inertia::render('PublicAgencies/Index', ['agencies' => $agencies]);
})->name('partners');

Route::get('/partners/{agency}', function (string $agency) {
    $agency = Agency::where('is_active', true)
        ->where(function ($q) use ($agency) {
            $q->where('slug', $agency);

            if (Str::isUuid($agency)) {
                $q->orWhere('id', $agency);
            }
        })
        ->firstOrFail();

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

Route::post('/contact', [ContactController::class, 'store'])
    ->name('contact.store')
    ->middleware(['turnstile', 'throttle:5,1']);

Route::get('/privacy', function () {
    return Inertia::render('Legal/PrivacyPolicy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Legal/TermsOfService');
})->name('terms');

Route::get('/track', [TrackController::class, 'index'])->name('track.index');
Route::post('/track/send-otp', [TrackController::class, 'sendOtp'])
    ->name('track.send-otp')
    ->middleware(['turnstile', 'throttle:tracking']);
Route::get('/track/verify-otp', function () {
    return redirect()->route('track.index');
})->name('track.verify-otp.get');
Route::post('/track/verify-otp', [TrackController::class, 'verifyOtp'])
    ->name('track.verify-otp')
    ->middleware('throttle:tracking');
Route::get('/track/case', [TrackController::class, 'show'])->name('track.show');
Route::get('/track/case/{tracker_number}/referrals/{referral}/milestones', [TrackController::class, 'milestones'])
    ->name('track.milestones');

Route::post('/track/request/exchange', [ReferralClientRequestController::class, 'exchange'])
    ->name('track.request.exchange')
    ->middleware('throttle:track-request-exchange');
Route::get('/track/request', [ReferralClientRequestController::class, 'show'])
    ->name('track.request.index');
Route::post('/track/request/messages', [ReferralClientRequestController::class, 'clientMessage'])
    ->name('track.request.messages.store')
    ->middleware('throttle:20,1');
Route::post('/track/request/replacement', [ReferralClientRequestController::class, 'replacement'])
    ->name('track.request.replacement')
    ->middleware('throttle:5,1');

Route::prefix('help')->name('helpdesk.')->group(function () {
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
    ->middleware(['turnstile.session', 'throttle:30,1']);

require __DIR__.'/auth.php';
