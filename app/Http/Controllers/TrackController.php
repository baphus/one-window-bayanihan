<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\SystemSetting;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrackController extends Controller
{
    public function __construct(
        private readonly TrackingService $trackingService,
    ) {}

    public function index()
    {
        return Inertia::render('Tracking/Portal');
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'tracker_number' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ]);

        $trackerNumber = $request->input('tracker_number');
        $email = strtolower(trim($request->input('email')));
        $case = $this->trackingService->findCaseByTracker($trackerNumber);
        if (! $case || ! $this->trackingService->emailMatchesCase($case, $email)) {
            $request->session()->forget(TrackingService::SESSION_KEY);

            // Return a generic error indistinguishable from other validation failures
            // to prevent tracker number enumeration
            return back()->withErrors(['tracker_number' => 'Unable to process request. Please check your details and try again.']);
        }

        $otp = $this->trackingService->generateOtp(
            $email,
            'track',
        );

        $emailParts = explode('@', $email);
        $hint = strlen($emailParts[0]) > 2
            ? substr($emailParts[0], 0, 2).str_repeat('*', strlen($emailParts[0]) - 2).'@'.$emailParts[1]
            : $email;

        return Inertia::render('Tracking/Verify', [
            'tracker_number' => $request->input('tracker_number'),
            'email' => $email,
            'hint' => $hint,
            'debug_otp' => (SystemSetting::getValue('debug_tracking_otp_enabled', false) && app()->environment('local', 'testing')) ? $otp : null,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'tracker_number' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $trackerNumber = $request->input('tracker_number');
        $email = strtolower(trim($request->input('email')));
        $case = $this->trackingService->findCaseByTracker($trackerNumber);
        if (! $case || ! $this->trackingService->emailMatchesCase($case, $email)) {
            $request->session()->forget(TrackingService::SESSION_KEY);

            return back()->withErrors(['tracker_number' => 'Unable to process request. Please check your details and try again.']);
        }

        $verified = $this->trackingService->verifyOtp(
            $email,
            $request->input('otp'),
            'track',
        );

        if (! $verified) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP.']);
        }

        $request->session()->regenerate();
        $request->session()->put(TrackingService::SESSION_KEY, [
            'tracker_number' => $case->tracker_number,
            'email' => $email,
        ]);

        return redirect()->route('track.show', ['tracker_number' => $case->tracker_number]);
    }

    public function show(Request $request)
    {
        $request->validate([
            'tracker_number' => ['required', 'string'],
        ]);

        if (! $this->trackingService->hasValidSessionBinding($request, $request->input('tracker_number'))) {
            return back()->withErrors(['tracker_number' => 'Unable to process request. Please check your details and try again.']);
        }

        $case = $this->trackingService->findCaseByTracker($request->input('tracker_number'));
        if (! $case) {
            // Return a generic error indistinguishable from other validation failures
            // to prevent tracker number enumeration
            return back()->withErrors(['tracker_number' => 'Unable to process request. Please check your details and try again.']);
        }

        $data = $this->trackingService->buildTrackingData($case);

        return Inertia::render('Tracking/Show', $data);
    }

    public function milestones(Request $request, string $tracker_number, Referral $referral)
    {
        if (! $this->trackingService->hasValidSessionBinding($request, $tracker_number)) {
            abort(404);
        }

        $case = $this->trackingService->findCaseByTracker($tracker_number);

        if (! $case || $referral->case_id !== $case->id) {
            abort(404);
        }

        $data = $this->trackingService->buildAgencyMilestonesData($case, $referral);

        return Inertia::render('Tracking/AgencyMilestones', $data);
    }
}
