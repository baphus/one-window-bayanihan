<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(private OnboardingService $service) {}

    /**
     * Get the current onboarding state for the authenticated user.
     */
    public function state(Request $request): JsonResponse
    {
        return response()->json($this->service->getOnboardingState($request->user()));
    }

    /**
     * Mark onboarding as skipped.
     */
    public function skip(Request $request): JsonResponse
    {
        $this->service->skipOnboarding($request->user());

        return response()->json(['ok' => true]);
    }

    /**
     * Mark onboarding as complete.
     */
    public function complete(Request $request): JsonResponse
    {
        $this->service->markOnboardingComplete($request->user());

        return response()->json(['ok' => true]);
    }

    /**
     * Reset onboarding state for replay.
     */
    public function replay(Request $request): JsonResponse
    {
        $this->service->resetOnboarding($request->user());

        return response()->json(['ok' => true]);
    }

    /**
     * Track the current step during the onboarding tour.
     * Step keys use the "<pageIndex>:<stepIndex>" format.
     */
    public function updateStep(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => 'required|string|max:32',
        ]);

        $this->service->updateStep($request->user(), $validated['step']);

        return response()->json(['ok' => true]);
    }

    /**
     * Mark a page guide as seen for the authenticated user.
     */
    public function markGuideSeen(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'route' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]+$/'],
        ]);

        $this->service->markGuideSeen($request->user(), $validated['route']);

        return response()->json(['ok' => true]);
    }

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
        $this->service->skipProfile($request->user());

        return redirect()->back()->with('success', 'Profile setup skipped.');
    }
}
