<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\AdminCaseCategoryController;
use App\Http\Controllers\Admin\AdminCaseIssueController;
use App\Http\Controllers\Admin\AdminCaseStatusController;
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
use App\Http\Controllers\HelpdeskArticleFeedbackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfwDashboardController;
use App\Http\Controllers\OfwProfileController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSurveyController;
use App\Http\Controllers\ReferralClientRequestController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReferralMessageController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\StakeholderController;
use App\Http\Controllers\SurveyFormController;
use App\Http\Controllers\SurveyResponseController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TemporaryFileController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\TrackRegistrationController;
use App\Models\Agency;
use App\Services\HelpdeskArticleService;
use App\Services\StorageService;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

// Public survey submission — no auth required (token-based)
Route::get('/survey/{token}', [PublicSurveyController::class, 'show'])
    ->name('survey.public.show')
    ->middleware('throttle:survey-view');
Route::post('/survey/{token}', [PublicSurveyController::class, 'submit'])
    ->name('survey.public.submit')
    ->middleware('throttle:survey-submit');

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Period-scoped summary for the case manager overview (7 / 30 / 90 days).
    Route::get('/dashboard/case-manager-period', [DashboardController::class, 'caseManagerPeriod'])
        ->name('dashboard.case-manager-period')
        ->middleware('throttle:api-global');

    // Period-scoped summary for the agency focal overview, scoped to the
    // signed-in user's own agency.
    Route::get('/dashboard/agency-period', [DashboardController::class, 'agencyPeriod'])
        ->name('dashboard.agency-period')
        ->middleware('throttle:api-global');

    // Period-scoped summary for the system admin overview.
    Route::get('/dashboard/admin-period', [DashboardController::class, 'adminPeriod'])
        ->name('dashboard.admin-period')
        ->middleware('throttle:api-global');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Profile picture. A dedicated endpoint so the avatar does not have to ride
    // along on the whole-record PATCH above, which requires name + email.
    Route::post('/profile/avatar', [ProfileController::class, 'storeAvatar'])->name('profile.avatar.store');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    Route::get('/profile/mfa/status', [MfaController::class, 'status'])->name('profile.mfa.status');
    Route::post('/profile/mfa/generate', [MfaController::class, 'generateSecret'])->name('profile.mfa.generate');
    Route::post('/profile/mfa/verify', [MfaController::class, 'verifyAndEnable'])->name('profile.mfa.verify');
    Route::post('/profile/mfa/disable', [MfaController::class, 'disable'])->name('profile.mfa.disable')->middleware('throttle:password-confirm');
    Route::post('/profile/mfa/recovery-codes/regenerate', [MfaController::class, 'regenerateRecoveryCodes'])->name('profile.mfa.recovery-codes.regenerate')->middleware('throttle:password-confirm');

    // Staff-only routes: referrals, reports, notifications.
    // The referral index/create and the reports pages below expose other
    // people's records (client addresses, system-wide aggregates, overdue
    // referral PII) and the reports exports were already refused OFW further
    // down in the controller, so the routes themselves are closed here too.
    Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index')->middleware('role:CASE_MANAGER,ADMIN,AGENCY');
    Route::get('/referrals/create', [ReferralController::class, 'create'])->name('referrals.create')->middleware('role:CASE_MANAGER,ADMIN,AGENCY');
    Route::post('/referrals', [ReferralController::class, 'store'])->name('referrals.store');
    Route::get('/referrals/export-excel', [ReferralController::class, 'exportExcel'])->name('referrals.export-excel')->middleware('role:CASE_MANAGER,ADMIN,AGENCY');
    Route::get('/referrals/export-count', [ReferralController::class, 'exportCount'])->name('referrals.export-count')->middleware('role:CASE_MANAGER,ADMIN,AGENCY');
    Route::get('/referrals/{referral}', [ReferralController::class, 'show'])->name('referrals.show')->middleware('role:CASE_MANAGER,ADMIN,AGENCY');
    Route::patch('/referrals/{referral}/status', [ReferralController::class, 'updateStatus'])->name('referrals.update-status');
    Route::delete('/referrals/{referral}', [ReferralController::class, 'destroy'])->name('referrals.destroy')->middleware('role:CASE_MANAGER,ADMIN');
    Route::post('/referrals/{referral}/milestones', [ReferralController::class, 'addMilestone'])->name('referrals.milestones.store');

    // Agency service management
    Route::post('/referrals/{referral}/services', [ReferralController::class, 'addService'])->name('referrals.services.add');
    Route::delete('/referrals/{referral}/services/{service}', [ReferralController::class, 'removeService'])->name('referrals.services.remove');

    // Agency service requirements management (per-referral)
    Route::post('/referrals/{referral}/services/{service}/requirements', [ReferralController::class, 'addRequirement'])->name('referrals.services.requirements.add');
    Route::patch('/referrals/{referral}/services/{service}/requirements/{requirement}', [ReferralController::class, 'updateRequirement'])->name('referrals.services.requirements.update');
    Route::delete('/referrals/{referral}/services/{service}/requirements/{requirement}', [ReferralController::class, 'deleteRequirement'])->name('referrals.services.requirements.delete');

    Route::post('/referrals/{referral}/comments', [ReferralController::class, 'addComment'])->name('referrals.comments.store');
    Route::post('/referrals/{referral}/comments/{comment}/reply', [ReferralController::class, 'replyToComment'])->name('referrals.comments.reply');

    // Agency message thread ("Other Agencies on This Case" section)
    Route::get('/api/referrals/{referral}/messages', [ReferralMessageController::class, 'index'])->name('api.referrals.messages.index')->middleware('throttle:api-global');
    Route::post('/referrals/{referral}/messages', [ReferralMessageController::class, 'store'])->name('referrals.messages.store');
    Route::post('/referrals/{referral}/messages/read', [ReferralMessageController::class, 'markRead'])->name('referrals.messages.read');
    Route::post('/referrals/{referral}/attachments', [ReferralController::class, 'addAttachment'])->name('referrals.attachments.store');
    Route::post('/referrals/{referral}/attachments/{attachment}/replace', [ReferralController::class, 'replaceAttachment'])->name('referrals.attachments.replace');
    Route::post('/referrals/{referral}/attachments/{attachment}/remove', [ReferralController::class, 'deleteAttachment'])->name('referrals.attachments.delete');
    Route::get('/referrals/{referral}/attachments/{attachment}/download', [ReferralController::class, 'downloadAttachment'])->name('referrals.attachments.download');
    Route::get('/referrals/{referral}/attachments/{versionGroupId}/versions', [ReferralController::class, 'getAttachmentVersions'])->name('referrals.attachments.versions');

    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index')->middleware(['role:CASE_MANAGER,ADMIN,AGENCY', 'throttle:reports-view']);
    // The exports are the expensive half of this controller (DomPDF + GD chart
    // images, PhpSpreadsheet) and php-fpm, the queue worker and scheduler share
    // one container, so they get their own limit rather than relying on the
    // generic web throttle. The index above keeps its own budget.
    Route::get('/reports/print', [ReportsController::class, 'print'])->name('reports.print')->middleware(['role:CASE_MANAGER,ADMIN,AGENCY', 'throttle:reports-export']);
    Route::get('/reports/export-pdf', [ReportsController::class, 'exportPdf'])->name('reports.export-pdf')->middleware(['role:CASE_MANAGER,ADMIN,AGENCY', 'throttle:reports-export']);
    Route::get('/reports/export-excel', [ReportsController::class, 'exportExcel'])->name('reports.export-excel')->middleware(['role:CASE_MANAGER,ADMIN,AGENCY', 'throttle:reports-export']);

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index')->withoutMiddleware('verified');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count')->withoutMiddleware('verified');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read')->withoutMiddleware('verified');
    Route::patch('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read')->withoutMiddleware('verified');
    Route::get('/notifications/page', function () {
        return Inertia::render('Notifications/Index');
    })->name('notifications.page');

    // Role-gated: CASE_MANAGER + ADMIN only
    Route::middleware('role:CASE_MANAGER,ADMIN')->group(function () {
        Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
        Route::get('/cases/review-pending', [CaseController::class, 'reviewPending'])->name('cases.review-pending');
        Route::get('/cases/trash', [CaseController::class, 'trashIndex'])->name('cases.trash');
        Route::get('/cases/export-excel', [CaseController::class, 'exportExcel'])->name('cases.export-excel');
        Route::get('/cases/export-count', [CaseController::class, 'exportCount'])->name('cases.export-count');
        Route::get('/cases/{case}/export-pdf', [CaseController::class, 'exportPdf'])->name('cases.export-pdf');
        Route::post('/cases/{case}/review-approve', [CaseController::class, 'reviewApprove'])->name('cases.review-approve');
        Route::post('/cases/{case}/review-reject', [CaseController::class, 'reviewReject'])->name('cases.review-reject');
        Route::post('/cases/{case}/archive', [CaseController::class, 'archive'])->name('cases.archive');
        Route::post('/cases/{case}/unarchive', [CaseController::class, 'unarchive'])->name('cases.unarchive');
        Route::delete('/cases/{case}/delete-archived', [CaseController::class, 'deleteArchived'])->name('cases.delete-archived');
        Route::post('/cases/{case}/restore', [CaseController::class, 'restore'])->name('cases.restore');
        Route::patch('/cases/{case}', [CaseController::class, 'update'])->name('cases.update');
        Route::post('/cases/{case}/toggle-status', [CaseController::class, 'toggleStatus'])->name('cases.toggle-status');

        Route::post('/case-issues/quick', [CaseIssueController::class, 'quickStore'])->name('case-issues.quick');

        Route::get('/stakeholders', [StakeholderController::class, 'index'])->name('stakeholders.index');
        Route::get('/stakeholders/{stakeholder}', [StakeholderController::class, 'show'])->name('stakeholders.show');

    });

    // Case filing: staff + OFW self-file. OFW create() prefills their own
    // linked client; store() resolves server-side via filer->client.
    Route::middleware('role:CASE_MANAGER,ADMIN,OFW')->group(function () {
        Route::get('/cases/create', [CaseController::class, 'create'])->name('cases.create');
        Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
    });

    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
        Route::get('/audit-logs/actors', [AuditLogController::class, 'actorOptions'])->name('audit-logs.actors');
    });

    Route::middleware('role:CASE_MANAGER,AGENCY,ADMIN')->group(function () {
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
        Route::get('/api/cases/{case}/activity', [ActivityController::class, 'caseActivity'])
            ->name('api.cases.activity')->middleware('throttle:api-global');
        Route::get('/api/referrals/{referral}/activity', [ActivityController::class, 'referralActivity'])
            ->name('api.referrals.activity')->middleware('throttle:api-global');
    });

    // Case show: AGENCY can view cases with active referrals (authorized in controller)
    Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show')
        ->middleware('role:CASE_MANAGER,ADMIN,AGENCY');

    Route::middleware('role:CASE_MANAGER,ADMIN,AGENCY')->group(function () {
        Route::get('/cases/{case}/documents', [CaseDocumentController::class, 'index'])->name('cases.documents.index');
        Route::get('/cases/{case}/documents/{document}', [CaseDocumentController::class, 'show'])->name('cases.documents.show');
        Route::get('/cases/{case}/documents/{document}/download', [CaseDocumentController::class, 'download'])->name('cases.documents.download');
    });

    Route::middleware('role:CASE_MANAGER,ADMIN')->group(function () {
        Route::post('/cases/{case}/documents', [CaseDocumentController::class, 'store'])->name('cases.documents.store');
        Route::delete('/cases/{case}/documents/{document}', [CaseDocumentController::class, 'destroy'])->name('cases.documents.destroy');
    });

    // Client routes: accessible to CASE_MANAGER, ADMIN, and AGENCY (controller handles per-role authorization)
    Route::middleware('role:CASE_MANAGER,ADMIN,AGENCY')->group(function () {
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/export-excel', [ClientController::class, 'exportExcel'])->name('clients.export-excel');
        Route::get('/clients/export-count', [ClientController::class, 'exportCount'])->name('clients.export-count');
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

    Route::middleware('role:ADMIN,AGENCY')->group(function () {
        Route::get('/referrals/{referral}/client-requests', [ReferralClientRequestController::class, 'index'])->name('referrals.client-requests.index');
        Route::patch('/client-request-attachments/{attachment}/review', [ReferralClientRequestController::class, 'reviewAttachment'])->name('referrals.client-requests.attachments.review');
        Route::get('/referrals/{referral}/client-requests/attachments/{attachment}/download', [ReferralClientRequestController::class, 'downloadAgencyAttachment'])->name('referrals.client-requests.attachments.download');
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
    Route::middleware('role:ADMIN,CASE_MANAGER')
        ->prefix('admin')->name('admin.')
        ->get('/agencies/{agency}', [AdminAgencyController::class, 'show'])
        ->name('agencies.show');

    // Onboarding routes (getting-started checklist + profile prompt only —
    // the guided tour is retired)
    Route::post('/onboarding/checklist/mark', [OnboardingController::class, 'markChecklistItem'])->name('onboarding.checklist.mark');
    Route::post('/onboarding/checklist/dismiss', [OnboardingController::class, 'dismissChecklist'])->name('onboarding.checklist.dismiss');
    Route::post('/onboarding/skip-profile', [OnboardingController::class, 'skipProfile'])->name('onboarding.skip-profile');

    Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
        Route::get('/helpdesk-feedback', [HelpdeskArticleFeedbackController::class, 'index'])->name('helpdesk-feedback.index');
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
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::post('/users/invite', [AdminUserController::class, 'invite'])->name('users.invite');
        // A browser refresh can land on the POST endpoint after an interrupted
        // Inertia visit; return admins to the users page instead of rendering 404.
        Route::get('/users/invite', fn () => redirect()->route('admin.users.index'));
        Route::post('/users/invites/{inviteId}/resend', [AdminUserController::class, 'resendInvite'])->name('users.invites.resend')->whereUuid('inviteId');
        Route::delete('/users/invites/{inviteId}', [AdminUserController::class, 'cancelInvite'])->name('users.invites.cancel')->whereUuid('inviteId');
        // No `throttle:password-confirm` here on purpose: it would also meter every
        // ordinary profile edit (this route carries the whole record, not just the
        // credential fields). AdminUserController::update() meters only the password
        // and email branches, which are the ones that re-check a password.
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update')->whereUuid('user');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show')->whereUuid('user');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy')->whereUuid('user');
        Route::patch('/users/{user}/reactivate', [AdminUserController::class, 'reactivate'])->name('users.reactivate')->whereUuid('user');
        Route::patch('/users/{user}/verify', [AdminUserController::class, 'verify'])->name('users.verify')->whereUuid('user');
        Route::post('/users/{user}/reset-mfa', [AdminUserController::class, 'resetMfa'])->name('users.reset-mfa')->whereUuid('user')->middleware('throttle:password-confirm');
        Route::post('/users/{user}/email-change/send-otp', [AdminUserController::class, 'sendEmailChangeOtp'])->name('users.email-change.send-otp')->whereUuid('user')->middleware('throttle:password-confirm');
        Route::post('/users/{user}/email-change/verify-otp', [AdminUserController::class, 'verifyEmailChangeOtp'])->name('users.email-change.verify-otp')->whereUuid('user')->middleware('throttle:password-confirm');

        Route::get('/system-settings', [SystemSettingsController::class, 'index'])->name('system-settings.index');
        Route::post('/system-settings', [SystemSettingsController::class, 'update'])->name('system-settings.update');
        Route::post('/system-settings/reindex-chatbot', [SystemSettingsController::class, 'reindexChatbot'])->name('system-settings.reindex-chatbot');

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

        Route::prefix('system')->name('system.')->group(function () {
            Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance');
            Route::post('/maintenance/toggle', [MaintenanceController::class, 'toggle'])->name('maintenance.toggle');

            Route::get('/security', [SecuritySettingsController::class, 'index'])->name('security');
            Route::post('/security', [SecuritySettingsController::class, 'update'])->name('security.update');
        });
    });
});

Route::middleware(['auth', 'role:ADMIN,CASE_MANAGER,AGENCY'])->group(function () {
    Route::get('/overdue-referrals', [OverdueReferralController::class, 'index'])->name('overdue-referrals.index');
    Route::post('/overdue-referrals/send-reminders', [OverdueReferralController::class, 'sendReminders'])->name('overdue-referrals.send-reminders');
});

Route::get('/partners', function () {
    $agencies = Agency::where('is_active', true)
        ->orderBy('name')
        ->paginate(50);

    // Services are loaded on the show route only; the cards render them
    // conditionally and the eager load dominated the payload.
    return Inertia::render('PublicAgencies/Index', [
        'agencies' => $agencies->getCollection()->toArray(),
    ]);
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
    ]);
})->name('partners.show');

Route::get('/contact', function () {
    return Inertia::render('Contact/Index');
})->name('contact');

Route::post('/contact', [ContactController::class, 'store'])
    ->name('contact.store')
    ->middleware(['turnstile', 'throttle:contact-form']);

Route::get('/privacy', function () {
    return Inertia::render('Legal/PrivacyPolicy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Legal/TermsOfService');
})->name('terms');

// Public OFW self-filing intake retired (account-first): OFWs register then
// file from their portal. /track/* OTP portal kept as read-only fallback.
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
Route::get('/track/case', [TrackController::class, 'show'])->name('track.show')
    ->middleware('throttle:tracking');
Route::get('/track/case/{tracker_number}/referrals/{referral}/milestones', [TrackController::class, 'milestones'])
    ->name('track.milestones')
    ->middleware('throttle:tracking');

Route::post('/track/register', [TrackRegistrationController::class, 'store'])
    ->name('track.register')
    ->middleware('throttle:intake-submit');

Route::post('/track/request/exchange', [ReferralClientRequestController::class, 'exchange'])
    ->name('track.request.exchange')
    ->middleware('throttle:track-request-exchange');
Route::get('/track/request', [ReferralClientRequestController::class, 'show'])
    ->name('track.request.index')
    ->middleware('throttle:tracking');
Route::post('/track/request/messages', [ReferralClientRequestController::class, 'clientMessage'])
    ->name('track.request.messages.store')
    ->middleware('throttle:track-request-message');
Route::post('/track/request/replacement', [ReferralClientRequestController::class, 'replacement'])
    ->name('track.request.replacement')
    ->middleware('throttle:track-request-replacement');

Route::post('/track/logout', function (Request $request) {
    $request->session()->forget(TrackingService::SESSION_KEY);

    return redirect()->route('track.index');
})->name('track.logout')->middleware('throttle:tracking');
Route::get('/track/request/attachments/{attachment}/download', [ReferralClientRequestController::class, 'downloadAttachment'])
    ->name('track.request.attachments.download')
    ->middleware('throttle:tracking');

Route::get('/help/{slug}/feedback', [HelpdeskArticleFeedbackController::class, 'show'])
    ->name('helpdesk.feedback.show')->middleware('throttle:helpdesk-feedback');
Route::post('/help/{slug}/feedback', [HelpdeskArticleFeedbackController::class, 'store'])
    ->name('helpdesk.feedback.store')->middleware('throttle:helpdesk-feedback');

Route::prefix('help')->name('helpdesk.')->group(function () {
    $render = function (Request $request, string $page, array $props = []) {
        $articles = app(HelpdeskArticleService::class)->allowedArticles($request->user());
        Inertia::encryptHistory();

        return inertia($page, [...$props, 'helpdeskArticles' => $articles])->toResponse($request)
            ->header('Cache-Control', 'private, no-store');
    };
    Route::get('/', fn (Request $request) => $render($request, 'Helpdesk/Index', [
        'category' => $request->query('category'),
    ]))->name('index');
    Route::get('/search', fn (Request $request) => $render($request, 'Helpdesk/Search', [
        'query' => $request->query('q', ''),
    ]))->name('search');
    Route::get('/{slug}', function (Request $request, string $slug) use ($render) {
        abort_if(app(HelpdeskArticleService::class)->findAllowed($slug, $request->user()) === null, 404);

        return $render($request, 'Helpdesk/Show', ['slug' => $slug]);
    })->name('show');
});

// Application-hosted expiring file links. StorageService falls back to these
// for disks whose driver cannot mint temporary URLs of its own (local and
// private), so downloads, attachments and avatars keep working the same way
// they do on object storage. The signature is the capability: the link is only
// minted after the caller authorized the download, and it may be opened in a
// new tab, so — exactly like an S3 presigned URL — there is no session or role
// check here. The disk and the path are both signed route parameters.
Route::get('/files/temp/{disk}/{path}', TemporaryFileController::class)
    ->name(StorageService::TEMP_FILE_ROUTE)
    ->where('path', '.*')
    ->middleware(['signed', 'throttle:60,1']);

// API routes (authenticated via web session) — in web.php for session middleware support
// Client picker PII: staff only. OFW self-file resolves filer->client
// server-side; NOK picks from the scoped my-linked-ofws endpoint below.
Route::middleware(['auth', 'verified', 'throttle:api-global', 'role:CASE_MANAGER,ADMIN'])->prefix('api')->group(function () {
    // Client selection for case creation form
    Route::get('/clients', [ClientSelectController::class, 'search'])->name('api.clients.index');
    Route::get('/clients/email-check', [ClientSelectController::class, 'checkEmail'])->name('api.clients.email-check');
    Route::get('/clients/{client}', [ClientSelectController::class, 'show'])->name('api.clients.show');

});

// NEXT_OF_KIN scoped dropdown: only OFW clients linked via ofw_client_id to
// the filer's own client. Empty when nothing linked yet — inline create is
// then the primary path.
Route::middleware(['auth', 'verified', 'throttle:api-global', 'role:OFW'])->prefix('api')->group(function () {
    Route::get('/my-linked-ofws', [ClientSelectController::class, 'linkedOfws'])->name('api.my-linked-ofws');
});

Route::post('/chatbot/message', [ChatbotController::class, 'message'])
    ->name('chatbot.message')
    ->middleware(['turnstile.session', 'throttle:chatbot']);

// OFW authenticated portal routes
Route::middleware(['auth', 'role:OFW'])->prefix('my-cases')->name('ofw.')->group(function () {
    Route::get('/', [OfwDashboardController::class, 'index'])->name('dashboard');
    Route::get('/notifications', [OfwDashboardController::class, 'notifications'])->name('notifications');
    Route::patch('/notifications/{id}/read', [OfwDashboardController::class, 'markNotificationAsRead'])->name('notifications.mark-as-read');
    Route::get('/intake', [CaseController::class, 'intake'])->name('intake');
    Route::post('/intake', [CaseController::class, 'storeIntake'])->name('intake.store');
    Route::get('/{case}/agencies/{referral}/milestones', [OfwDashboardController::class, 'agencyMilestones'])->name('case.milestones');
    Route::get('/profile', [OfwProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [OfwProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [OfwProfileController::class, 'storeAvatar'])->name('profile.avatar.store');
    Route::delete('/profile/avatar', [OfwProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::get('/{id}', [OfwDashboardController::class, 'show'])->name('case.show');
});

require __DIR__.'/auth.php';
