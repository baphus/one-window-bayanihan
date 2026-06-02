<?php

namespace App\Services\Chatbot;

use App\Models\CaseFile;
use App\Services\OtpService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Auth-aware case queries for the AI chatbot.
 *
 * Respects three access levels:
 * 1. Authenticated users (case manager, agency, admin) → full case access
 * 2. OTP-verified OFWs → access to their specific case
 * 3. Anonymous users → no case data access
 */
class ChatbotCaseService
{
    private const OTP_PURPOSE = 'chatbot_case_verify';

    private const SESSION_KEY = 'chatbot_verified_cases';

    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    /**
     * Check if the current request has an authenticated user.
     */
    public function isAuthenticated(): bool
    {
        return auth()->check();
    }

    /**
     * Check if the authenticated user has staff-level access (not just OFW/public).
     */
    public function isStaffUser(): bool
    {
        if (! $this->isAuthenticated()) {
            return false;
        }

        $user = auth()->user();

        return $user->isCaseManager() || $user->isAdmin() || $user->agcy_id !== null;
    }

    /**
     * Get the authenticated user's role description for the AI prompt.
     */
    public function getUserAuthContext(): string
    {
        if (! $this->isAuthenticated()) {
            return 'anonymous_public';
        }

        $user = auth()->user();

        if ($user->isAdmin()) {
            return 'admin';
        }

        if ($user->isCaseManager()) {
            return 'case_manager';
        }

        if ($user->agcy_id) {
            return 'agency_focal';
        }

        return 'authenticated_unknown';
    }

    /**
     * Search cases that the authenticated user has access to.
     *
     * @return array{success: bool, data?: Collection, message?: string}
     */
    public function searchCases(string $query, int $limit = 5): array
    {
        if (! $this->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'You need to be logged in to search cases. Please log in to your account first.',
            ];
        }

        $user = auth()->user();

        try {
            $caseQuery = CaseFile::query()
                ->where('is_deleted', false)
                ->with(['client', 'user']);

            // Role-based filtering
            if ($user->isAdmin()) {
                // Admin sees all cases - no filter
            } elseif ($user->isCaseManager()) {
                // Case manager sees their own cases
                $caseQuery->where('user_id', $user->id);
            } elseif ($user->agcy_id) {
                // Agency sees cases referred to them
                $caseQuery->whereHas('referrals', function ($q) use ($user) {
                    $q->where('agcy_id', $user->agcy_id);
                });
            } else {
                return [
                    'success' => false,
                    'message' => 'Your account does not have permission to search cases.',
                ];
            }

            // Search filter
            if (! empty($query)) {
                $caseQuery->where(function ($q) use ($query) {
                    $q->whereRaw('LOWER(case_number) LIKE ?', ['%'.mb_strtolower($query).'%'])
                        ->orWhereRaw('LOWER(tracker_number) LIKE ?', ['%'.mb_strtolower($query).'%'])
                        ->orWhere('cases.id', 'LIKE', "%{$query}%")
                        ->orWhereHas('client', function ($cq) use ($query) {
                            $cq->whereRaw('LOWER(first_name) LIKE ?', ['%'.mb_strtolower($query).'%'])
                                ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.mb_strtolower($query).'%']);
                        });
                });
            }

            // Limit results
            $cases = $caseQuery->latest()->take($limit)->get();

            $results = $cases->map(fn (CaseFile $c) => [
                'id' => $c->id,
                'case_number' => $c->case_number,
                'tracker_number' => $c->tracker_number,
                'status' => $c->status,
                'client_name' => $c->client
                    ? trim($c->client->first_name.' '.$c->client->last_name)
                    : 'Unknown',
                'created_at' => $c->created_at?->toDateString(),
            ]);

            return [
                'success' => true,
                'data' => $results,
            ];
        } catch (\Exception $e) {
            Log::warning('Chatbot case search failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while searching cases. Please try again.',
            ];
        }
    }

    /**
     * Get detailed info for a specific case.
     *
     * @return array{success: bool, data?: array, message?: string}
     */
    public function getCaseDetail(string $caseId): array
    {
        if (! $this->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'You need to be logged in to view case details.',
            ];
        }

        $user = auth()->user();

        try {
            $case = CaseFile::with(['client', 'user', 'referrals.agency'])->find($caseId);

            if (! $case || $case->is_deleted) {
                return [
                    'success' => false,
                    'message' => 'Case not found.',
                ];
            }

            // Access check
            if (! $user->isAdmin()) {
                if ($user->isCaseManager() && $case->user_id !== $user->id) {
                    return [
                        'success' => false,
                        'message' => 'You do not have access to this case.',
                    ];
                }

                if ($user->agcy_id) {
                    $hasReferral = $case->referrals()->where('agcy_id', $user->agcy_id)->exists();
                    if (! $hasReferral) {
                        return [
                            'success' => false,
                            'message' => 'You do not have access to this case.',
                        ];
                    }
                }
            }

            $client = $case->client;
            $referrals = $case->referrals->map(fn ($r) => [
                'id' => $r->id,
                'required_services' => $r->required_services,
                'status' => $r->status,
                'agency' => $r->agency?->name ?? 'Unknown',
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $case->id,
                    'case_number' => $case->case_number,
                    'tracker_number' => $case->tracker_number,
                    'status' => $case->status,
                    'summary' => $case->summary,
                    'client_type' => $case->client_type,
                    'client' => $client ? [
                        'name' => trim($client->first_name.' '.$client->last_name),
                        'email' => $client->email,
                        'contact_number' => $client->contact_number,
                    ] : null,
                    'case_manager' => $case->user?->name ?? 'Unknown',
                    'created_at' => $case->created_at?->toDateTimeString(),
                    'referrals' => $referrals,
                ],
            ];
        } catch (\Exception $e) {
            Log::warning('Chatbot case detail failed', [
                'error' => $e->getMessage(),
                'case_id' => $caseId,
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while fetching case details.',
            ];
        }
    }

    /**
     * Initiate OTP verification for a case via tracker number.
     * Sends OTP to the OFW's registered email.
     *
     * @return array{success: bool, message: string, email?: string}
     */
    public function initiateCaseOTP(string $trackerNumber): array
    {
        $case = CaseFile::where('tracker_number', $trackerNumber)
            ->where('is_deleted', false)
            ->first();

        if (! $case) {
            return [
                'success' => false,
                'message' => 'No case found with that tracker number. Please check and try again.',
            ];
        }

        $client = $case->client;
        $email = $client?->email;

        if (! $email) {
            return [
                'success' => false,
                'message' => 'No email address is registered for this case. Please contact your case manager directly.',
            ];
        }

        try {
            $this->otpService->generate($email, self::OTP_PURPOSE);

            return [
                'success' => true,
                'message' => "A 6-digit verification code has been sent to the email address on file for this case ({$email}). Please check your email and enter the code to view your case details.",
                'email_masked' => $this->maskEmail($email),
            ];
        } catch (\Exception $e) {
            Log::warning('Chatbot OTP send failed', [
                'error' => $e->getMessage(),
                'tracker' => $trackerNumber,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send verification code. Please try again later.',
            ];
        }
    }

    /**
     * Verify OTP for a case and mark it as verified in the session.
     *
     * @return array{success: bool, message: string, case_data?: array}
     */
    public function verifyCaseOTP(string $trackerNumber, string $otp): array
    {
        $case = CaseFile::where('tracker_number', $trackerNumber)
            ->where('is_deleted', false)
            ->first();

        if (! $case) {
            return [
                'success' => false,
                'message' => 'No case found with that tracker number.',
            ];
        }

        $client = $case->client;
        $email = $client?->email;

        if (! $email) {
            return [
                'success' => false,
                'message' => 'No email registered for this case.',
            ];
        }

        $verified = $this->otpService->verify($email, self::OTP_PURPOSE, $otp);

        if (! $verified) {
            return [
                'success' => false,
                'message' => 'Invalid or expired verification code. Please request a new code.',
            ];
        }

        // Store verified tracker in session
        $verifiedCases = session()->get(self::SESSION_KEY, []);
        $verifiedCases[] = $trackerNumber;
        session()->put(self::SESSION_KEY, array_unique($verifiedCases));

        return [
            'success' => true,
            'message' => 'Verification successful! Here are your case details:',
            'case_data' => [
                'case_number' => $case->case_number,
                'tracker_number' => $case->tracker_number,
                'status' => $case->status,
                'summary' => $case->summary,
                'client_name' => $client
                    ? trim($client->first_name.' '.$client->last_name)
                    : 'Unknown',
                'created_at' => $case->created_at?->toDateString(),
                'referrals' => $case->referrals()->with('agency')->get()->map(fn ($r) => [
                    'required_services' => $r->required_services,
                    'status' => $r->status,
                    'agency' => $r->agency?->name ?? 'Unknown',
                ]),
            ],
        ];
    }

    /**
     * Get case info for a previously OTP-verified tracker number.
     *
     * @return array{success: bool, data?: array, message?: string}
     */
    public function getVerifiedCaseInfo(string $trackerNumber): array
    {
        $verifiedCases = session()->get(self::SESSION_KEY, []);

        if (! in_array($trackerNumber, $verifiedCases)) {
            return [
                'success' => false,
                'message' => 'This tracker number has not been verified. Please use initiateCaseOTP to request a verification code first.',
            ];
        }

        $case = CaseFile::where('tracker_number', $trackerNumber)
            ->where('is_deleted', false)
            ->with(['client', 'referrals.agency'])
            ->first();

        if (! $case) {
            return [
                'success' => false,
                'message' => 'Case not found.',
            ];
        }

        $client = $case->client;

        return [
            'success' => true,
            'data' => [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'tracker_number' => $case->tracker_number,
                'status' => $case->status,
                'summary' => $case->summary,
                'client_name' => $client
                    ? trim($client->first_name.' '.$client->last_name)
                    : 'Unknown',
                'created_at' => $case->created_at?->toDateString(),
                'referrals' => $case->referrals->map(fn ($r) => [
                    'required_services' => $r->required_services,
                    'status' => $r->status,
                    'agency' => $r->agency?->name ?? 'Unknown',
                ]),
            ],
        ];
    }

    /**
     * Mask an email for display (e.g., j***@example.com).
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';

        $maskedName = mb_substr($name, 0, 1).str_repeat('*', max(0, mb_strlen($name) - 1));

        return $maskedName.'@'.$domain;
    }
}
