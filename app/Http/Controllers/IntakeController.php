<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIntakeRequest;
use App\Models\SystemSetting;
use App\Services\IntakeService;
use App\Services\ReferenceDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IntakeController extends Controller
{
    public function __construct(
        private readonly IntakeService $intakeService,
        private readonly ReferenceDataService $referenceData,
    ) {}

    /**
     * Show the public intake form.
     */
    public function index()
    {
        return Inertia::render('Intake/Index', [
            'occupationOptions' => $this->referenceData->getOccupationOptions(),
        ]);
    }

    /**
     * Send OTP to verify OFW email ownership.
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($request->input('email')));

        $otp = $this->intakeService->generateOtp($email);

        $emailParts = explode('@', $email);
        $hint = strlen($emailParts[0]) > 2
            ? substr($emailParts[0], 0, 2).str_repeat('*', strlen($emailParts[0]) - 2).'@'.$emailParts[1]
            : $email;

        $debugOtp = (SystemSetting::getValue('debug_tracking_otp_enabled', false) && app()->environment('local', 'testing'))
            ? $otp
            : null;

        return response()->json([
            'sent' => true,
            'hint' => $hint,
            'debug_otp' => $debugOtp,
        ]);
    }

    /**
     * Verify the OTP and check for duplicates.
     */
    public function checkDuplicate(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $email = strtolower(trim($request->input('email')));

        $verified = $this->intakeService->verifyOtp($email, $request->input('otp'));

        if (! $verified) {
            return response()->json([
                'verified' => false,
                'error' => 'Invalid or expired OTP.',
            ], 422);
        }

        // Mark email as verified in session
        $request->session()->put('intake_verified_email', $email);

        // Check for duplicates
        $result = $this->intakeService->checkDuplicate($email);

        return response()->json([
            'verified' => true,
            'duplicate' => $result['duplicate'],
            'message' => $result['message'],
            'existing_client' => $result['existing_client'] ? [
                'first_name' => $result['existing_client']->first_name,
                'last_name' => $result['existing_client']->last_name,
                'middle_initial' => $result['existing_client']->middle_initial,
                'suffix' => $result['existing_client']->suffix,
                'date_of_birth' => $result['existing_client']->date_of_birth?->toDateString(),
                'sex' => $result['existing_client']->sex,
                'contact_number' => $result['existing_client']->contact_number,
                'address' => $result['existing_client']->addresses->first()?->only([
                    'region', 'province', 'city_municipality', 'barangay', 'street',
                ]),
                'employment' => $result['existing_client']->employments->first()?->only([
                    'employer_name', 'position', 'country', 'start_date', 'end_date',
                    'last_country', 'last_position', 'date_of_arrival',
                ]),
                'next_of_kin' => $result['existing_client']->nextOfKin->map(fn ($nok) => $nok->only([
                    'first_name', 'last_name', 'middle_initial', 'relationship',
                    'phone_number', 'email', 'region', 'province', 'city_municipality',
                    'barangay', 'street',
                ]))->values()->toArray(),
            ] : null,
        ]);
    }

    /**
     * Process the full intake submission.
     */
    public function submit(StoreIntakeRequest $request)
    {
        $verifiedEmail = $request->session()->get('intake_verified_email');

        if (! $verifiedEmail) {
            return response()->json([
                'error' => 'Email not verified. Please verify your email first.',
            ], 422);
        }

        // Final duplicate check before submission
        $duplicateCheck = $this->intakeService->checkDuplicate($verifiedEmail);
        if ($duplicateCheck['duplicate']) {
            return response()->json([
                'error' => $duplicateCheck['message'],
            ], 409);
        }

        $case = $this->intakeService->createIntakeCase(
            $request->validated(),
            $verifiedEmail,
        );

        // Clear the verified email from session
        $request->session()->forget('intake_verified_email');

        return response()->json([
            'success' => true,
            'message' => 'Your request has been submitted successfully. A Case Manager will review your information.',
        ]);
    }
}
