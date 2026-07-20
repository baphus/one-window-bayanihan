<?php

namespace App\Providers;

use App\Http\Middleware\HandleInertiaRequests;
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
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientRequest;
use App\Models\ReferralClientRequestItem;
use App\Models\Service;
use App\Models\ServiceRequirement;
use App\Models\SurveyInvitation;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Observers\CacheInvalidationObserver;
use App\Services\Contracts\MalwareScannerInterface;
use App\Services\Malware\ClamAvScanner;
use App\Services\Malware\NullScanner;
use Cloudinary\Configuration\Configuration;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
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
            Agency::class,
            User::class,
            Service::class,
            ServiceRequirement::class,
            CaseCategory::class,
            CaseIssue::class,
            CaseStatus::class,
            ReferralClientRequest::class,
            ReferralClientRequestItem::class,
            ReferralClientMessage::class,
            ReferralClientAccessLink::class,
        ];

        foreach ($auditableModels as $model) {
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
            SurveyInvitation::class,
            Milestone::class,
        ];

        foreach ($cacheableModels as $model) {
            $model::observe(CacheInvalidationObserver::class);
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

            return filled($email)
                ? Limit::perHour(5)->by('recipient|'.hash('sha256', strtolower(trim($email))))
                : Limit::none();
        });

        RateLimiter::for('track-request-exchange', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        Event::listen(Login::class, LogSuccessfulLogin::class);
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
