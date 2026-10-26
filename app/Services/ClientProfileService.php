<?php

namespace App\Services;

use App\Models\Client;
use App\Models\NextOfKin;

/**
 * Owns client identity rows and their child records (address, employment,
 * next of kin). Extracted from IntakeService/CaseService so account-first
 * filing has one place that creates and syncs client profiles.
 */
class ClientProfileService
{
    public function __construct(
        private readonly PhilippineAddressService $addressService,
    ) {}

    /**
     * @param  array<string, mixed>  $data  client fields + address/employment/next_of_kin nests
     */
    public function createClient(array $data, ?string $userId = null, string $type = 'OFW'): Client
    {
        $client = Client::create([
            'type' => $type,
            'user_id' => $userId,
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'middle_name' => $data['middle_name'] ?? null,
            'suffix' => $data['suffix'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'sex' => ! empty($data['sex']) ? strtoupper($data['sex']) : null,
            'email' => $data['email'] ?? null,
            'contact_number' => $data['contact_number'] ?? null,
        ]);

        $this->syncAddress($client, $data['address'] ?? null);
        $this->syncEmployment($client, $data['employment'] ?? null);
        $this->syncNextOfKin($client, $data['next_of_kin'] ?? null);

        return $client;
    }

    public function findClientByEmail(string $email): ?Client
    {
        // Client.email is plaintext, so the match can run in the database.
        return Client::where('is_deleted', false)
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])
            ->first();
    }

    public function syncAddress(Client $client, ?array $addressData): void
    {
        if (empty($addressData)) {
            return;
        }

        $resolved = $this->resolveAddressNames($addressData);

        $client->addresses()->updateOrCreate(['client_id' => $client->id], $resolved);
    }

    public function syncEmployment(Client $client, ?array $employmentData): void
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

        // Deterministic pick: same-transaction inserts share created_at, so the
        // id tiebreak keeps registration and /my-cases/profile on the same row.
        $employment = $client->employments()->orderBy('created_at')->orderBy('id')->first();

        if ($employment) {
            $employment->update($empData);
        } else {
            $client->employments()->create($empData);
        }
    }

    public function syncNextOfKin(Client $client, mixed $nokData): void
    {
        if (empty($nokData)) {
            return;
        }

        // Handle single-object format
        if (isset($nokData['first_name'])) {
            $nokData = [$nokData];
        }

        // Soft-delete sync: keep ids, update/create, soft-delete rows missing
        // from the submission, keep one primary.
        $existingIds = $client->nextOfKin()->pluck('id')->toArray();
        $incomingIds = array_filter(array_column($nokData, 'id'));
        $idsToDelete = array_diff($existingIds, $incomingIds);

        if (! empty($idsToDelete)) {
            $client->nextOfKin()->whereIn('id', $idsToDelete)->each(fn ($n) => $n->delete());
        }

        foreach ($nokData as $index => $nok) {
            if (empty($nok['first_name']) && empty($nok['last_name'])) {
                continue;
            }

            $attributes = [
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
                'is_primary' => $nok['is_primary'] ?? $index === 0,
                'sort_order' => $nok['sort_order'] ?? $index,
            ];

            if (! empty($nok['id'])) {
                $existing = $client->nextOfKin()->find($nok['id']);
                if ($existing) {
                    $existing->update($attributes);

                    continue;
                }
            }

            $client->nextOfKin()->create($attributes);
        }

        $this->ensureSinglePrimary($client->id);
    }

    public function ensureSinglePrimary(string $clientId): void
    {
        $noks = NextOfKin::where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        $primaryCount = $noks->where('is_primary', true)->count();

        if ($primaryCount === 0 && $noks->isNotEmpty()) {
            $noks->first()->update(['is_primary' => true]);

            return;
        }

        if ($primaryCount > 1) {
            $noks->where('is_primary', true)->slice(1)->each(fn ($n) => $n->update(['is_primary' => false]));
        }
    }

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
