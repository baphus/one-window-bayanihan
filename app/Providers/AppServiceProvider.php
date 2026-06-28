<?php

namespace App\Providers;

use App\Events\ReferralCompleted;
use App\Listeners\EmailEventSubscriber;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\SendFeedbackRequest;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\Service;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Services\Contracts\MalwareScannerInterface;
use App\Services\Malware\ClamAvScanner;
use App\Services\Malware\NullScanner;
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
            Agency::class,
            User::class,
            Service::class,
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
        Event::listen(ReferralCompleted::class, SendFeedbackRequest::class);

        Event::subscribe(EmailEventSubscriber::class);
    }
}
