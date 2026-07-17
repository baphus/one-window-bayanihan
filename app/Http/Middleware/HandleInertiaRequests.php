<?php

namespace App\Http\Middleware;

use App\Helpers\CacheHelper;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        if (app()->environment('local')) {
            return null;
        }

        if (app()->environment('local')) {
            return '';
        }

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

    /**
     * Get cached unread notification count for the user.
     * TTL: 60 seconds — avoids a COUNT query on every single page load.
     */
    private function getUnreadNotificationCount(Request $request): int
    {
        $user = $request->user();
        if (! $user) {
            return 0;
        }

        return (int) CacheHelper::safeRemember(
            "notifications:unread:{$user->id}",
            60,
            fn () => $user->unreadNotifications()->count(),
        );
    }

    /**
     * Get cached agency for the authenticated user.
     * TTL: 1 hour — user's agency assignment rarely changes.
     */
    private function getCachedUserAgency(Request $request): mixed
    {
        $user = $request->user();
        if (! $user || ! $user->agcy_id) {
            return null;
        }

        return CacheHelper::safeRemember(
            "user:{$user->id}:agency",
            3600,
            fn () => $user->agency,
        );
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->only([
                        'id', 'name', 'email', 'role', 'agcy_id', 'avatar_url',
                        'is_active', 'contact_number', 'position', 'department',
                        'office_location', 'bio', 'timezone',
                        'onboarding_completed_at', 'onboarding_step',
                        'profile_completed_at',
                    ]),
                    'agency' => $this->getCachedUserAgency($request),
                ] : null,
            ],
            'notifications' => fn () => [
                'unread_count' => $this->getUnreadNotificationCount($request),
            ],
            'just_published' => $request->session()->get('just_published'),
            'onboarding_required' => fn () => $this->getOnboardingRequired($request),
            'onboarding' => fn () => $request->user()
                ? app(OnboardingService::class)->getOnboardingState($request->user())
                : null,
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
                'provider' => config('ai-chatbot.provider', 'gemini'),
                'assistant_name' => config('ai-chatbot.assistant_name', 'Bayani'),
            ],
            'turnstile' => [
                'enabled' => (bool) config('turnstile.enabled', false),
                'site_key' => config('turnstile.site_key', ''),
            ],
        ];
    }

    // ── Cache Invalidation Helpers ───────────────────────────────────────

    /**
     * Invalidate the cached unread count for a specific user.
     * Call this when notifications are created or marked as read.
     */
    public static function invalidateNotificationCount(string $userId): void
    {
        cache()->forget("notifications:unread:{$userId}");
    }

    /**
     * Invalidate the cached agency for a specific user.
     * Call this when the user's agency assignment changes.
     */
    public static function invalidateUserAgency(string $userId): void
    {
        cache()->forget("user:{$userId}:agency");
    }
}
