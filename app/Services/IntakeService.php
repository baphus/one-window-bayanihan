<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Notifications\NewIntakeSubmission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class IntakeService
{
    public function __construct(
        private readonly CaseService $caseService,
        private readonly PhilippineAddressService $addressService,
        private readonly OtpService $otpService,
        private readonly CaseNumberGenerator $caseNumbers,
    ) {}

    /**
     * Check if the given email has an active (OPEN or DRAFT) case.
     *
     * @return array{duplicate: bool, existing_client: ?Client, message: ?string}
     */
    public function checkDuplicate(string $email): array
    {
        // Check if an OFW user account exists with an active case
        $existingUser = User::where('email', $email)
            ->where('role', 'OFW')
            ->first();

        if ($existingUser && $existingUser->client_id) {
            $hasActiveCase = CaseFile::where('client_id', $existingUser->client_id)
                ->whereIn('status', ['OPEN', 'DRAFT'])
                ->where('is_deleted', false)
                ->exists();

            if ($hasActiveCase) {
                return [
                    'duplicate' => true,
                    'existing_client' => null,
                    'message' => 'You already have an active case. Please use the tracking portal to check its status.',
                ];
            }

            // Returning OFW with only closed cases — allow, return client for pre-fill
            return [
                'duplicate' => false,
                'existing_client' => Client::with(['addresses', 'employments', 'nextOfKin'])
                    ->find($existingUser->client_id),
                'message' => null,
            ];
        }

        // Check clients table for matching email (OFW filed by CM previously, no account)
        $matchingClient = $this->findClientByEmail($email);

        if ($matchingClient) {
            $hasActiveCase = CaseFile::where('client_id', $matchingClient->id)
                ->whereIn('status', ['OPEN', 'DRAFT'])
                ->where('is_deleted', false)
                ->exists();

            if ($hasActiveCase) {
                return [
                    'duplicate' => true,
                    'existing_client' => null,
                    'message' => 'You already have an active case. Please use the tracking portal to check its status.',
                ];
            }

            return [
                'duplicate' => false,
                'existing_client' => $matchingClient->load(['addresses', 'employments', 'nextOfKin']),
                'message' => null,
            ];
        }

        return [
            'duplicate' => false,
            'existing_client' => null,
            'message' => null,
        ];
    }

    /**
     * Create the self-filed intake case: find/create the client, create the draft case.
     * No User account is created — tracking is handled via OTP + tracker number.
     */
    public function createIntakeCase(array $data, string $verifiedEmail): CaseFile
    {
        // Normalize employment: the self-filing form only collects
        // last_position/last_country. Populate position/country so the
        // review page and published client record have them filled in.
        if (! empty($data['employment'])) {
            if (empty($data['employment']['position']) && ! empty($data['employment']['last_position'])) {
                $data['employment']['position'] = $data['employment']['last_position'];
            }
            if (empty($data['employment']['country']) && ! empty($data['employment']['last_country'])) {
                $data['employment']['country'] = $data['employment']['last_country'];
            }
        }

        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $case = DB::transaction(function () use ($data, $verifiedEmail) {
                    $client = $this->findOrCreateClient($data, $verifiedEmail);
                    $case = $this->createDraftCase($data, $client->id, $verifiedEmail);

                    return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin']);
                });
                // Success — exit retry loop
                break;
            } catch (QueryException $e) {
                if ($e->getCode() === '23505' && $attempt < $maxAttempts) {
                    // Unique constraint violation (case_number or tracker_number collision) — retry
                    continue;
                }

                throw $e;
            }
        }

        // Notify all active Case Managers about the new intake (outside transaction)
        $this->notifyCaseManagers($case, $data);

        // Audit log the self-filed intake submission
        AuditLog::create([
            'action' => AuditAction::CREATE->value,
            'module' => AuditModule::CASE->value,
            'entity_id' => $case->id,
            'description' => 'OFW self-filed intake submitted',
            'user_id' => null,
            'timestamp' => now(),
        ]);

        return $case;
    }

    /**
     * Find existing client by email or create a new one.
     */
    public function findOrCreateClient(array $data, string $email): Client
    {
        // Try to find existing client by email
        $existingClient = $this->findClientByEmail($email);

        if ($existingClient) {
            // Update client profile with any new data
            $existingClient->update(array_filter([
                'first_name' => $data['client']['first_name'] ?? null,
                'last_name' => $data['client']['last_name'] ?? null,
                'middle_name' => $data['client']['middle_name'] ?? null,
                'suffix' => $data['client']['suffix'] ?? null,
                'date_of_birth' => $data['client']['date_of_birth'] ?? null,
                'sex' => ! empty($data['client']['sex']) ? strtoupper($data['client']['sex']) : null,
                'contact_number' => $data['client']['contact_number'] ?? null,
            ], fn ($v) => $v !== null));

            $this->syncAddress($existingClient, $data['address'] ?? null);
            $this->syncEmployment($existingClient, $data['employment'] ?? null);
            $this->syncNextOfKin($existingClient, $data['next_of_kin'] ?? null);

            return $existingClient;
        }

        // Create new client
        $client = Client::create([
            'first_name' => $data['client']['first_name'] ?? '',
            'last_name' => $data['client']['last_name'] ?? '',
            'middle_name' => $data['client']['middle_name'] ?? null,
            'suffix' => $data['client']['suffix'] ?? null,
            'date_of_birth' => $data['client']['date_of_birth'] ?? null,
            'sex' => ! empty($data['client']['sex']) ? strtoupper($data['client']['sex']) : null,
            'email' => $email,
            'contact_number' => $data['client']['contact_number'] ?? null,
        ]);

        $this->syncAddress($client, $data['address'] ?? null);
        $this->syncEmployment($client, $data['employment'] ?? null);
        $this->syncNextOfKin($client, $data['next_of_kin'] ?? null);

        return $client;
    }

    /**
     * Generate OTP for intake email verification.
     */
    public function generateOtp(string $email): string
    {
        return $this->otpService->generate($email, 'intake');
    }

    /**
     * Verify OTP for intake email verification.
     */
    public function verifyOtp(string $email, string $otp): bool
    {
        return $this->otpService->verify($email, 'intake', $otp);
    }

    /**
     * Notify all active Case Managers about a new intake submission.
     */
    private function notifyCaseManagers(CaseFile $case, array $data): void
    {
        $ofwName = trim(($data['client']['first_name'] ?? '').' '.($data['client']['last_name'] ?? ''));

        $caseManagers = User::where('role', 'CASE_MANAGER')
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->get();

        if ($caseManagers->isNotEmpty()) {
            Notification::send($caseManagers, new NewIntakeSubmission($case, $ofwName));
        }
    }

    /**
     * Find a client record by email match.
     */
    private function findClientByEmail(string $email): ?Client
    {
        // Client.email is plaintext, so the match can run in the database.
        // LOWER(TRIM()) keeps it case- and whitespace-insensitive, mirroring
        // the previous PHP-side strtolower(trim()) comparison without loading
        // every non-deleted client into memory.
        return Client::where('is_deleted', false)
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])
            ->first();
    }

    /**
     * Create the draft case record for a self-filed intake.
     */
    private function createDraftCase(array $data, string $clientId, string $email): CaseFile
    {
        // Build a complete snapshot for the review page.
        // ReviewIntake reads draft.address, draft.employment, draft.next_of_kin
        // and draft.email directly from this JSON, so all submitted data must live here.
        $clientData = $data['client'] ?? [];
        $clientData['email'] = $email;

        if (! empty($data['address'])) {
            $clientData['address'] = $data['address'];
        }
        if (! empty($data['employment'])) {
            $clientData['employment'] = $data['employment'];
        }
        if (! empty($data['next_of_kin'])) {
            $clientData['next_of_kin'] = $data['next_of_kin'];
        }
        if (! empty($data['vulnerability'])) {
            $clientData['vulnerability'] = $data['vulnerability'];
        }

        $case = CaseFile::create([
            'case_number' => $this->generateCaseNumber(),
            'tracker_number' => $this->generateTrackerNumber(),
            'client_type' => 'OFW',
            'summary' => $data['summary'] ?? null,
            'status' => 'DRAFT',
            'source' => CaseFile::SOURCE_SELF_FILED,
            'consent_given_at' => ! empty($data['consent']) ? now() : null,
            'user_id' => null,
            'client_id' => $clientId,
            'draft_client_data' => $clientData,
        ]);

        return $case;
    }

    private function syncAddress(Client $client, ?array $addressData): void
    {
        if (empty($addressData)) {
            return;
        }

        $resolved = $this->resolveAddressNames($addressData);
        $address = $client->addresses()->first();

        if ($address) {
            $address->update($resolved);
        } else {
            $client->addresses()->create($resolved);
        }
    }

    private function syncEmployment(Client $client, ?array $employmentData): void
    {
        if (empty($employmentData)) {
            return;
        }

        $empData = [
            'employer_name' => $employmentData['employer_name'] ?? null,
            'position' => $employmentData['position'] ?? null,
            'country' => $employmentData['country'] ?? null,
            'start_date' => $employmentData['start_date'] ?? null,
            'end_date' => ! empty($employmentData['is_present']) ? null : ($employmentData['end_date'] ?? null),
            'last_country' => $employmentData['last_country'] ?? null,
            'last_position' => $employmentData['last_position'] ?? null,
            'date_of_arrival' => $employmentData['date_of_arrival'] ?? null,
        ];

        $employment = $client->employments()->first();

        if ($employment) {
            $employment->update($empData);
        } else {
            $client->employments()->create($empData);
        }
    }

    private function syncNextOfKin(Client $client, ?array $nokData): void
    {
        if (empty($nokData)) {
            return;
        }

        // Handle single-object format
        if (isset($nokData['first_name'])) {
            $nokData = [$nokData];
        }

        // Delete existing NOK and recreate (simpler for intake)
        $client->nextOfKin()->each(fn ($n) => $n->forceDelete());

        foreach ($nokData as $index => $nok) {
            if (empty($nok['first_name']) && empty($nok['last_name'])) {
                continue;
            }

            $client->nextOfKin()->create([
                'first_name' => $nok['first_name'] ?? null,
                'last_name' => $nok['last_name'] ?? null,
                'middle_name' => $nok['middle_name'] ?? null,
                'relationship' => $nok['relationship'] ?? null,
                'phone_number' => $nok['phone_number'] ?? null,
                'email' => $nok['email'] ?? null,
                'region' => $nok['region'] ?? $nok['nok_address']['region'] ?? null,
                'province' => $nok['province'] ?? $nok['nok_address']['province'] ?? null,
                'city_municipality' => $nok['city_municipality'] ?? $nok['nok_address']['city_municipality'] ?? null,
                'barangay' => $nok['barangay'] ?? $nok['nok_address']['barangay'] ?? null,
                'street' => $nok['street'] ?? $nok['nok_address']['street'] ?? null,
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Both identifiers come from CaseNumberGenerator, the single implementation
     * shared with CaseService.
     *
     * These were previously private duplicates here, and they had already drifted:
     * the tracker returned strtoupper(bin2hex(random_bytes(4))) — eight hex
     * characters with no OWBAP- prefix — under a docblock claiming it mirrored
     * CaseService, so every self-filed OFW received an identifier the tracking
     * portal and helpdesk told them to type differently. Sharing one
     * implementation is what stops that recurring.
     */
    private function generateCaseNumber(): string
    {
        return $this->caseNumbers->nextCaseNumber();
    }

    private function generateTrackerNumber(): string
    {
        return $this->caseNumbers->nextTrackerNumber();
    }

    /**
     * Resolve address PSGC codes to human-readable names.
     */
    private function resolveAddressNames(array $address): array
    {
        $codes = array_filter([
            $address['region'] ?? null,
            $address['province'] ?? null,
            $address['city_municipality'] ?? null,
            $address['barangay'] ?? null,
        ]);

        if (empty($codes)) {
            return $address;
        }

        $names = $this->addressService->resolveNames($codes);

        foreach (['region', 'province', 'city_municipality', 'barangay'] as $key) {
            if (isset($address[$key])) {
                $address[$key] = $names[$address[$key]] ?? $address[$key];
            }
        }

        return $address;
    }
}
