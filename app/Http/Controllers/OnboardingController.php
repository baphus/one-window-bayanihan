<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(private OnboardingService $service) {}

    /**
     * Mark a getting-started checklist item complete.
     */
    public function markChecklistItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
        ]);

        $this->service->markChecklistItem($request->user(), $validated['item']);

        return response()->json(['ok' => true]);
    }

    /**
     * Dismiss the getting-started checklist.
     */
    public function dismissChecklist(Request $request): JsonResponse
    {
        $this->service->dismissChecklist($request->user());

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────
    //  Profile Completion (first-time info prompt)
    // ─────────────────────────────────────────────

    /**
     * Skip the profile info prompt without filling in details.
     * Still Inertia-driven (DashboardBanner uses router.post).
     */
    public function skipProfile(Request $request)
    {
        $this->service->markProfileComplete($request->user());

        return redirect()->back()->with('success', 'Profile setup skipped.');
    }
}
