<?php

namespace App\Providers;

use App\Listeners\LogSuccessfulLogin;
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
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
        ];

        foreach ($auditableModels as $model) {
            $model::observe(AuditObserver::class);
        }

        Event::listen(Login::class, LogSuccessfulLogin::class);
    }
}
