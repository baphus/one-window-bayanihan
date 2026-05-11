<?php

namespace App\Http\Controllers;

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
            'tracker_number' => ['required', 'string', 'exists:cases,tracker_number'],
            'email' => ['required', 'string', 'email'],
        ]);

        $case = $this->trackingService->findCaseByTracker($request->input('tracker_number'));
        if (!$case) {
            return back()->withErrors(['tracker_number' => 'Invalid tracker number.']);
        }

        $otp = $this->trackingService->generateOtp(
            $request->input('email'),
            'track',
        );

        logger("OTP for {$request->input('email')} (tracking {$request->input('tracker_number')}): {$otp}");

        $emailParts = explode('@', $request->input('email'));
        $hint = strlen($emailParts[0]) > 2
            ? substr($emailParts[0], 0, 2) . str_repeat('*', strlen($emailParts[0]) - 2) . '@' . $emailParts[1]
            : $request->input('email');

        return Inertia::render('Tracking/Verify', [
            'tracker_number' => $request->input('tracker_number'),
            'email' => $request->input('email'),
            'hint' => $hint,
            'debug_otp' => SystemSetting::getValue('debug_otp_enabled', false) ? $otp : null,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'tracker_number' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $verified = $this->trackingService->verifyOtp(
            $request->input('email'),
            $request->input('otp'),
            'track',
        );

        if (!$verified) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP.']);
        }

        $case = $this->trackingService->findCaseByTracker($request->input('tracker_number'));
        if (!$case) {
            return back()->withErrors(['tracker_number' => 'Case not found.']);
        }

        $data = $this->trackingService->buildTrackingData($case);

        return Inertia::render('Tracking/Show', $data);
    }

    public function show(Request $request)
    {
        $request->validate([
            'tracker_number' => ['required', 'string', 'exists:cases,tracker_number'],
        ]);

        $case = $this->trackingService->findCaseByTracker($request->input('tracker_number'));
        if (!$case) {
            return back()->withErrors(['tracker_number' => 'Case not found.']);
        }

        $data = $this->trackingService->buildTrackingData($case);

        return Inertia::render('Tracking/Show', $data);
    }
}
