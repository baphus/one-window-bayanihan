<?php

namespace App\Http\Controllers;

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
        ]);

        $case = $this->trackingService->findCaseByTracker($request->input('tracker_number'));
        if (!$case) {
            return back()->withErrors(['tracker_number' => 'Invalid tracker number.']);
        }

        $otp = $this->trackingService->generateOtp($request->input('tracker_number'));

        logger("OTP for {$request->input('tracker_number')}: {$otp}");

        return Inertia::render('Tracking/Verify', [
            'tracker_number' => $request->input('tracker_number'),
            'hint' => strlen($request->input('tracker_number')) > 6
                ? substr($request->input('tracker_number'), -4)
                : null,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'tracker_number' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $verified = $this->trackingService->verifyOtp(
            $request->input('tracker_number'),
            $request->input('otp'),
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
