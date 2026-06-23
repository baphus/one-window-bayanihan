<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * Get the current onboarding state for the authenticated user.
     */
    public function state(Request $request): JsonResponse
    {
        $service = app(OnboardingService::class);
        $state = $service->getOnboardingState($request->user());

        return response()->json($state);
    }

    /**
     * Mark onboarding as skipped.
     * Returns a redirect (not JSON) so Inertia v2 router.post() fires
     * onSuccess after the follow-up GET completes, unblocking the promise
     * that endTour() depends on.
     */
    public function skip(Request $request)
    {
        $service = app(OnboardingService::class);
        $service->skipOnboarding($request->user());

        return redirect()->back()->with('success', 'Onboarding skipped.');
    }

    /**
     * Mark onboarding as complete.
     */
    public function complete(Request $request)
    {
        $service = app(OnboardingService::class);
        $service->markOnboardingComplete($request->user());

        return redirect()->back()->with('success', 'Onboarding completed.');
    }

    /**
     * Reset onboarding state for replay.
     */
    public function replay(Request $request)
    {
        $service = app(OnboardingService::class);
        $service->resetOnboarding($request->user());

        return redirect()->back()->with('success', 'Onboarding reset for replay.');
    }
}
