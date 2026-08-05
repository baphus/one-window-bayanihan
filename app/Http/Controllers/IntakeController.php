<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIntakeRequest;
use App\Models\Client;
use App\Models\SystemSetting;
use App\Services\IntakeService;
use App\Services\PhilippineAddressService;
use App\Services\ReferenceDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IntakeController extends Controller
{
    public function __construct(
        private readonly IntakeService $intakeService,
        private readonly ReferenceDataService $referenceData,
        private readonly PhilippineAddressService $addressService,
    ) {}

    /**
     * Show the public intake form.
     *
     * When the visitor is a signed-in OFW with a linked client record, the
     * wizard is pre-filled with that client's profile so filing a follow-up
     * case does not mean retyping everything. Every field stays editable —
     * this is an initial value, not a lock.
     */
    public function index()
    {
        $existingClient = null;

        $user = request()->user();
        if ($user && $user->client_id) {
            $client = Client::with(['addresses', 'employments', 'nextOfKin'])
                ->find($user->client_id);

            if ($client) {
                $existingClient = $this->formatExistingClient($client);
                $existingClient['email'] = $user->email;
            }
        }

        return Inertia::render('Intake/Index', [
            'occupationOptions' => $this->referenceData->getOccupationOptions(),
            'existingClient' => $existingClient,
        ]);
    }

    /**
     * Shape a client record into the intake wizard's pre-fill payload.
     *
     * Shared by the signed-in pre-fill (index) and the returning-OFW pre-fill
     * (checkDuplicate) so both paths hand the wizard identical data. Client
     * addresses are stored as display names, but the wizard's dropdowns are
     * keyed by PSGC code, so each address is converted back to codes here.
     */
    private function formatExistingClient(Client $client): array
    {
        $address = $client->addresses->first();
        $employment = $client->employments->first();

        return [
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'middle_initial' => $client->middle_initial,
            'suffix' => $client->suffix,
            'date_of_birth' => $client->date_of_birth?->toDateString(),
            'sex' => $client->sex ? ucfirst(strtolower($client->sex)) : null,
            'contact_number' => $client->contact_number,
            'address' => $address ? ($this->addressService->resolveAddressToCodes([
                'region' => $address->region,
                'province' => $address->province,
                'city_municipality' => $address->city_municipality,
                'barangay' => $address->barangay,
            ]) + ['street' => $address->street]) : null,
            'employment' => $employment ? [
                'employer_name' => $employment->employer_name,
                'position' => $employment->position,
                'country' => $employment->country,
                'start_date' => $employment->start_date?->toDateString(),
                'end_date' => $employment->end_date?->toDateString(),
                'is_present' => $employment->end_date === null,
                'last_country' => $employment->last_country,
                'last_position' => $employment->last_position,
                'date_of_arrival' => $employment->date_of_arrival?->toDateString(),
            ] : null,
            'next_of_kin' => $client->nextOfKin->map(function ($nok, int $index) {
                $resolved = $this->addressService->resolveAddressToCodes([
                    'region' => $nok->region,
                    'province' => $nok->province,
                    'city_municipality' => $nok->city_municipality,
                    'barangay' => $nok->barangay,
                ]);

                return array_merge($nok->only([
                    'first_name', 'last_name', 'middle_initial', 'relationship',
                    'phone_number', 'email', 'region', 'province', 'city_municipality',
                    'barangay', 'street',
                ]), $resolved, ['is_primary' => $index === 0]);
            })->values()->toArray(),
        ];
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
            'existing_client' => $result['existing_client']
                ? $this->formatExistingClient($result['existing_client'])
                : null,
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

        // Keep verified email in session for potential account creation
        // (IntakeRegistrationController will clear it after use)

        return response()->json([
            'success' => true,
            'message' => 'Your request has been submitted successfully. A Case Manager will review your information.',
            'case_number' => $case->case_number,
            'tracker_number' => $case->tracker_number,
            'email' => $verifiedEmail,
        ]);
    }
}
