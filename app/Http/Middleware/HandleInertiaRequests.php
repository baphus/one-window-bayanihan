<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'notifications' => [
                'unread_count' => $request->user()
                    ? $request->user()->unreadNotifications()->count()
                    : 0,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                'status' => $request->session()->get('status'),
            ],
            'settings' => [
                'debug_otp_enabled' => SystemSetting::getValue('debug_otp_enabled', false),
            ],
            'chatbot' => [
                'enabled' => SystemSetting::getValue('chatbot_enabled', false) === 'true' || SystemSetting::getValue('chatbot_enabled', false) === true,
                'provider' => SystemSetting::getValue('chatbot_provider', 'openai'),
            ],
        ];
    }
}
