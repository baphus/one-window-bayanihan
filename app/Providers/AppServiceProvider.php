<?php

namespace App\Providers;

use App\Listeners\EmailEventSubscriber;
use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\CaseStatus;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\Feedback;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralComplianceRequirement;
use App\Models\Service;
use App\Models\ServiceRequirement;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Services\Contracts\MalwareScannerInterface;
use App\Services\Malware\ClamAvScanner;
use App\Services\Malware\NullScanner;
use Cloudinary\Configuration\Configuration;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MalwareScannerInterface::class, function ($app) {
            return env('MALWARE_SCANNER', 'null') === 'clamav'
                ? new ClamAvScanner
                : new NullScanner;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceRootUrl(config('app.url'));

        if (! app()->environment('local', 'testing')) {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);

        Password::defaults(function () {
            return Password::min(8)->mixedCase()->numbers()->symbols();
        });

        $auditableModels = [
            CaseFile::class,
            Client::class,
            ClientAddress::class,
            ClientEmployment::class,
            Referral::class,
            Milestone::class,
            ReferralAttachment::class,
            ReferralComplianceRequirement::class,
            Agency::class,
            User::class,
            Service::class,
            ServiceRequirement::class,
            CaseCategory::class,
            CaseIssue::class,
            CaseStatus::class,
            Feedback::class,
        ];

        foreach ($auditableModels as $model) {
            $model::observe(AuditObserver::class);
        }

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->input('email') ?: $request->ip());
        });

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)->by(($request->input('email', '') ?: 'anonymous').'|'.$request->ip());
        });

        RateLimiter::for('tracking', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('totp-challenge', function (Request $request) {
            return Limit::perMinute(3)->by(
                ($request->session()->get('pending_mfa_user_id', 'guest'))
                .'|'.$request->ip()
            );
        });

        RateLimiter::for('recovery-code', function (Request $request) {
            return Limit::perMinute(3)->by(
                ($request->session()->get('pending_mfa_user_id', 'guest'))
                .'|'.$request->ip()
            );
        });

        RateLimiter::for('api-global', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-mutations', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Failed::class, LogFailedLogin::class);
        // SendFeedbackRequest is auto-discovered (handle() type-hints ReferralCompleted);
        // registering it here as well made it run twice per completion and violate
        // the feedback_invitations unique constraint.

        Event::subscribe(EmailEventSubscriber::class);

        // Set Cloudinary API timeout to prevent hanging uploads
        if (class_exists(Configuration::class)) {
            Configuration::instance()
                ->api->timeout = 30;
        }
    }
}
