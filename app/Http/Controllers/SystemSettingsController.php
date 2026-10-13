<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;

class SystemSettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('SystemSettings/Index', [
            'referral_overdue_days' => (int) SystemSetting::getValue('referral_overdue_days', 7),
            'chatbot_last_reindexed_at' => SystemSetting::getValue('chatbot_last_reindexed_at'),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'referral_overdue_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        if ($request->has('referral_overdue_days')) {
            SystemSetting::setValue('referral_overdue_days', (int) $request->input('referral_overdue_days'));
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    /**
     * Refresh the parsed chatbot helpdesk content cache.
     *
     * Runs synchronously — the corpus is small (~300 sections) and completes
     * in under 30 seconds. Returns JSON for the frontend polling handler.
     */
    public function reindexChatbot(): JsonResponse
    {
        try {
            $exitCode = Artisan::call('chatbot:index');

            if ($exitCode !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Helpdesk cache refresh failed. Check the server logs for details.',
                ], 500);
            }

            SystemSetting::setValue('chatbot_last_reindexed_at', now()->toIso8601String());

            return response()->json([
                'success' => true,
                'message' => 'Chatbot helpdesk content refreshed successfully.',
                'last_reindexed_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Chatbot helpdesk cache refresh failed', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Helpdesk cache refresh failed. Please try again.',
            ], 500);
        }
    }
}
