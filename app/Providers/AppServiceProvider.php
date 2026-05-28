<?php

namespace App\Providers;

use App\Contracts\HelpCenterProviderInterface;
use App\Listeners\LogSuccessfulLogin;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\HelpdeskArticle;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\Service;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Services\HelpCenter\EloquentHelpCenterProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HelpCenterProviderInterface::class, EloquentHelpCenterProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

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
            HelpdeskArticle::class,
        ];

        foreach ($auditableModels as $model) {
            $model::observe(AuditObserver::class);
        }

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->input('email') ?: $request->ip());
        });

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email') ?: $request->ip());
        });

        RateLimiter::for('tracking', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        Event::listen(Login::class, LogSuccessfulLogin::class);
    }
}
