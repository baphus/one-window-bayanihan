<?php

namespace App\Providers;

use App\Http\Middleware\HandleInertiaRequests;
use App\Listeners\EmailEventSubscriber;
use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\CaseStatus;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralClientRequest;
use App\Models\Service;
use App\Models\ServiceRequirement;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Observers\CacheInvalidationObserver;
use App\Services\Contracts\MalwareScannerInterface;
use App\Services\Malware\ClamAvScanner;
use App\Services\Malware\NullScanner;
use Cloudinary\Configuration\Configuration;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Set once the disabled-scanner warning has been emitted, so the warning
     * logs once per process instead of on every container boot (tests spin up
     * the provider repeatedly).
     */
    private static bool $malwareScannerWarningLogged = false;

    /**
     * Logged once per process, at first boot, when no malware scanner is
     * configured. The fail-open default is silent by design for dev; the
     * warning line makes it visible in every environment's logs so a
     * production deploy without MALWARE_SCANNER=clamav cannot slip through
     * unnoticed.
     *
     * Best-effort: the StreamHandler opens the log file on the first write,
     * so when another local process holds storage/logs/laravel.log open
     * without sharing (Windows queues/watchers), logging throws while we
     * still have no LogFacility to fall back to. A visibility warning must
     * never prevent the application from booting, so a failed write is
     * swallowed and retried on the next boot.
     */
    private static function warnIfMalwareScannerDisabled(): void
    {
        if (self::$malwareScannerWarningLogged) {
            return;
        }

        $scanner = (string) config('services.malware.scanner', 'null');

        if ($scanner === 'clamav') {
            return;
        }

        try {
            Log::warning('Malware scanner is DISABLED: uploaded files are accepted UNSCANNED (fail-open). Set MALWARE_SCANNER=clamav for any non-development deployment.', [
                'scanner' => $scanner,
                'fail_open' => (bool) config('services.malware.fail_open', true),
            ]);

            self::$malwareScannerWarningLogged = true;
        } catch (\Throwable) {
            // Log destination unavailable — retry on the next boot.
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        self::warnIfMalwareScannerDisabled();

        $this->app->bind(MalwareScannerInterface::class, function ($app) {
            return config('services.malware.scanner', 'null') === 'clamav'
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

        // Audited models are declared in config/audit.php (single source of
        // truth, asserted by AuditModelCoverageTest).
        foreach (config('audit.observed_models', []) as $model) {
            $model::observe(AuditObserver::class);
        }

        // Cache invalidation observer for models that affect cached reference data/stats
        $cacheableModels = [
            Agency::class,
            CaseCategory::class,
            CaseIssue::class,
            CaseStatus::class,
            User::class,
            CaseFile::class,
            Referral::class,
            Service::class,
            ServiceRequirement::class,
            Milestone::class,
        ];

        foreach ($cacheableModels as $model) {
            $model::observe(CacheInvalidationObserver::class);
        }

        // Per-email and per-IP, for the same reason as password-reset-request
        // below: the email field is attacker-controlled and always present, so
        // the `?: $request->ip()` fallback this replaced could never fire. One
        // host could therefore take a fresh password guess at an invented
        // address every ten requests and spray the whole directory, while
        // LoginRequest::throttleKey()'s per-pair limit never noticed because each
        // guess used a different address. The per-IP limit is what caps the
        // sprayer; the per-email limit keeps one account's attackers out of
        // everyone else's budget, and is the key LoginRequest::authenticate()
        // clears on a successful sign-in.
        //
        // The per-IP ceiling is deliberately above the per-account one, and it
        // counts successful sign-ins too. Staff share office egress IPs, so a
        // per-IP number as low as the per-account one would lock out a whole
        // floor during a morning login rush; thirty a minute still caps a
        // sprayer at a bounded number of guesses per host per minute, which is
        // the point of the limit.
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(10)->by(($request->input('email', '') ?: 'anonymous').'|'.$request->ip()),
                Limit::perMinute(30)->by('ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(10)->by($request->input('email') ?: $request->ip());
        });

        // Keyed on the identity that owns the code, not on the pending-registration
        // email alone: profile.email-change.send-otp / verify-otp reach this same
        // limiter while authenticated, so a session-only key left every one of
        // them reading `guest|<ip>` and put a whole office behind one router in
        // a single five-per-minute bucket. Authenticated callers now key on
        // their own user id; guest registration still falls through to the
        // pending email so a filer cannot rotate the field for a fresh budget.
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)->by(
                ($request->user()?->id
                    ?: $request->session()->get('pending_registration.credentials.email', 'guest')
                ).'|'.$request->ip()
            );
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

        /*
         * Every limiter below replaces an inline `throttle:N,1` on a route a
         * guest can reach. Inline limits are not per-route: for an
         * unauthenticated request ThrottleRequests keys the counter as
         * sha1(domain|ip), so all of them shared a single budget per visitor and
         * the smallest limit among them capped the sum of the rest. The OFW
         * self-filing wizard broke on exactly that — its own address-dropdown
         * lookups spent the five requests /intake/submit was allowed, so the
         * filer's submission came back 429. Naming a limiter gives it its own
         * key, so a route's budget is spent only by that route. Keep new
         * guest-facing limits named for the same reason; the numbers here are
         * the ones the inline limits already used.
         */
        /*
         * Two limits, both enforced. Keying only on email|ip would be weaker than
         * the inline per-IP limit this replaced: one host could send five codes
         * per minute for every address it invented, so rotating the field bought
         * unbounded outbound mail to third parties. The per-email limit stops one
         * inbox being flooded; the per-IP limit caps the sender.
         */
        RateLimiter::for('intake-otp', function (Request $request) {
            return [
                Limit::perMinute(5)->by(($request->input('email', '') ?: 'anonymous').'|'.$request->ip()),
                Limit::perMinute(5)->by('ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('intake-duplicate', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('intake-submit', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('chatbot', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('contact-form', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('csp-report', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('readiness', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('resend-webhook', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });

        RateLimiter::for('survey-view', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('survey-submit', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('track-request-message', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('track-request-replacement', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('invite-register', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Per-email and per-IP, for the same reason as intake-otp: without the
        // per-IP limit, rotating the email field gives unbounded reset mail and a
        // free account-enumeration oracle.
        RateLimiter::for('password-reset-request', function (Request $request) {
            return [
                Limit::perMinute(5)->by(($request->input('email', '') ?: 'anonymous').'|'.$request->ip()),
                Limit::perMinute(5)->by('ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('email-verification', function (Request $request) {
            return Limit::perMinute(6)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('reports-view', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // The PDF and Excel report renders are the heaviest request in the app
        // and php-fpm, the queue worker and the scheduler share one container,
        // so a single account refreshing the export link could starve the queue
        // for everyone. Five a minute per user is far above one person working.
        RateLimiter::for('reports-export', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-global', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-mutations', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // These endpoints re-check the account password before acting (MFA
        // disable, recovery-code regenerate, admin reset-MFA and email change),
        // so each hit is a password guess that must never be free to repeat.
        // Five a minute per user is far above one person confirming themselves,
        // and it stops a hijacked session brute-forcing its way past the check.
        RateLimiter::for('password-confirm', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        $recipientEmail = static function (Request $request): ?string {
            $referral = $request->route('referral');
            if ($referral instanceof Referral) {
                return $referral->loadMissing('caseFile.client')->caseFile?->client?->email;
            }

            if (filled($referral)) {
                return Referral::query()->with('caseFile.client')->find($referral)?->caseFile?->client?->email;
            }

            $clientRequest = $request->route('clientRequest');
            if ($clientRequest instanceof ReferralClientRequest) {
                return $clientRequest->loadMissing('referral.caseFile.client')->referral?->caseFile?->client?->email;
            }

            if (filled($clientRequest)) {
                return ReferralClientRequest::query()->with('referral.caseFile.client')->find($clientRequest)?->referral?->caseFile?->client?->email;
            }

            return null;
        };

        RateLimiter::for('agency-client-request-create', function (Request $request) {
            $referral = $request->route('referral');
            $referralId = is_object($referral) ? $referral->getKey() : $referral;

            return Limit::perHour(5)->by(($request->user()?->id ?: 'guest').'|'.$referralId);
        });

        RateLimiter::for('agency-client-access', function (Request $request) {
            $clientRequest = $request->route('clientRequest');
            $requestId = is_object($clientRequest) ? $clientRequest->getKey() : $clientRequest;

            return Limit::perHour(3)->by(($request->user()?->id ?: 'guest').'|'.$requestId);
        });

        RateLimiter::for('agency-client-delivery-recipient', function (Request $request) use ($recipientEmail) {
            $email = $recipientEmail($request);

            // Was: Limit::none() when the client has no email on file. That
            // silently removed this whole limit class for exactly the client
            // records most likely to be re-requested repeatedly, leaving only
            // the other two throttles on those routes. Key on the request id
            // so the brake still exists when there is no recipient address.
            return filled($email)
                ? Limit::perHour(5)->by('recipient|'.hash('sha256', strtolower(trim($email))))
                : Limit::perHour(5)->by('recipient|'.$request->user()?->id.'|'.$request->ip());
        });

        RateLimiter::for('track-request-exchange', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(Failed::class, LogFailedLogin::class);
        // SendSurveyRequest is auto-discovered (handle() type-hints ReferralCompleted);
        // registering it here as well would make it run twice per completion and violate
        // the survey_invitations unique constraint.

        Event::subscribe(EmailEventSubscriber::class);

        // Invalidate cached notification count when a database notification is sent
        Event::listen(NotificationSent::class, function ($event) {
            if ($event->channel === 'database' && $event->notifiable && method_exists($event->notifiable, 'getKey')) {
                $key = $event->notifiable->getKey();
                if ($key !== null) {
                    HandleInertiaRequests::invalidateNotificationCount($key);
                }
            }
        });

        // Set Cloudinary API timeout to prevent hanging uploads
        if (class_exists(Configuration::class)) {
            Configuration::instance()
                ->api->timeout = 30;
        }
    }
}
