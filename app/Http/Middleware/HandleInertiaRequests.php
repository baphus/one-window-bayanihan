<?php

namespace App\Http\Middleware;

use App\Services\AlertService;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    private function getOnboardingRequired(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return app(OnboardingService::class)->isOnboardingRequired($user);
    }

    private function getProfileIncomplete(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return app(OnboardingService::class)->isProfileIncomplete($user);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only([
                    'id', 'name', 'email', 'role', 'agcy_id', 'avatar_url',
                    'is_active', 'contact_number', 'position', 'department',
                    'office_location', 'bio', 'timezone',
                    'onboarding_completed_at', 'onboarding_step',
                    'profile_completed_at',
                ]),
            ],
            'alert_count' => $request->user()
                ? app(AlertService::class)->getActiveAlerts($request->user())['unread_count']
                : 0,
            'notifications' => [
                'unread_count' => $request->user()
                    ? $request->user()->unreadNotifications()->count()
                    : 0,
            ],
            'just_published' => $request->session()->get('just_published'),
            'onboarding_required' => fn () => $this->getOnboardingRequired($request),
            'profile_incomplete' => fn () => $this->getProfileIncomplete($request),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                'status' => $request->session()->get('status'),
            ],
            'chatbot' => [
                'enabled' => config('ai-chatbot.enabled', false),
                'provider' => config('ai-chatbot.provider', 'openai'),
            ],
            'turnstile' => [
                'enabled' => (bool) config('turnstile.enabled', false),
                'site_key' => config('turnstile.site_key', ''),
            ],
        ];
    }
}
