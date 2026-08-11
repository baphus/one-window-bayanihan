<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use App\Notifications\CaseUpdated;
use DateTimeInterface;

/**
 * Persists the self-service profile sections an OFW may edit themselves
 * (contact number, address, employment, next of kin) and notifies the client's
 * case managers when something actually changed.
 *
 * Identity-critical fields (name, date of birth, sex) and the account email are
 * deliberately never accepted here — those remain staff-owned and read-only in
 * the OFW UI.
 */
class OfwProfileService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Apply whatever profile sections are present in the payload. Returns nothing;
     * the audit trail is written by AuditObserver on the observed models, and the
     * case-manager notification is dispatched only when a section changed.
     */
    public function updateClientProfile(User $user, array $data): void
    {
        $client = $user->client;

        if (! $client) {
            return;
        }

        $changes = [];

        if (array_key_exists('contact_number', $data)) {
            $this->syncContactNumber($user, $client, $data['contact_number'], $changes);
        }

        if (isset($data['address']) && is_array($data['address'])) {
            $this->syncAddress($client, $data['address'], $changes);
        }

        if (isset($data['employment']) && is_array($data['employment'])) {
            $this->syncEmployment($client, $data['employment'], $changes);
        }

        if (isset($data['next_of_kin']) && is_array($data['next_of_kin'])) {
            $this->syncNextOfKin($client, $data['next_of_kin'], $changes);
        }

        $user->save();

        if (! empty($changes)) {
            $this->notifyCaseManagers($client, $user, $changes);
        }
    }

    private function syncContactNumber(User $user, Client $client, mixed $newContact, array &$changes): void
    {
        $newContact = $this->normalize($newContact);

        if ((string) ($client->contact_number ?? '') !== (string) ($newContact ?? '')) {
            $changes['Contact number'] = [
                'old' => $client->contact_number,
                'new' => $newContact,
            ];

            $client->contact_number = $newContact;
            $client->save();
        }

        // Keep the login account in sync with the client record.
        $user->contact_number = $newContact;
    }

    private function syncAddress(Client $client, array $address, array &$changes): void
    {
        $fields = ['region', 'province', 'city_municipality', 'barangay', 'street'];

        $row = $client->addresses()->orderBy('created_at')->first();

        if (! $row) {
            if ($this->isEmptyFields($address, $fields)) {
                return;
            }

            $new = $this->pickFields($address, $fields);
            $client->addresses()->create($new);
            $changes['Address'] = ['old' => null, 'new' => $new];

            return;
        }

        $dirty = $this->diffFields($row, $address, $fields);

        if (empty($dirty)) {
            return;
        }

        foreach ($dirty as $field => $_) {
            $row->{$field} = $dirty[$field]['new'];
        }
        $row->save();

        $changes['Address'] = $dirty;
    }

    private function syncEmployment(Client $client, array $employment, array &$changes): void
    {
        $fields = ['employer_name', 'position', 'country', 'start_date', 'end_date', 'last_country', 'last_position', 'date_of_arrival'];

        $row = $client->employments()->orderBy('created_at')->first();

        if (! $row) {
            if ($this->isEmptyFields($employment, $fields)) {
                return;
            }

            $new = $this->pickFields($employment, $fields);
            $client->employments()->create($new);
            $changes['Employment'] = ['old' => null, 'new' => $new];

            return;
        }

        $dirty = $this->diffFields($row, $employment, $fields);

        if (empty($dirty)) {
            return;
        }

        foreach ($dirty as $field => $_) {
            $row->{$field} = $dirty[$field]['new'];
        }
        $row->save();

        $changes['Employment'] = $dirty;
    }

    private function syncNextOfKin(Client $client, array $nokList, array &$changes): void
    {
        // Accept a single-object payload as a one-row list (intake legacy shape).
        if (isset($nokList['first_name']) || isset($nokList['last_name'])) {
            $nokList = [$nokList];
        }

        $nokList = array_values(array_filter(
            $nokList,
            fn ($nok) => is_array($nok) && (! empty($nok['first_name']) || ! empty($nok['last_name'])),
        ));

        if ($client->nextOfKin()->count() === 0 && empty($nokList)) {
            return;
        }

        // Replace the list wholesale (same strategy as IntakeService::syncNextOfKin).
        $client->nextOfKin()->each(fn ($nok) => $nok->forceDelete());

        foreach ($nokList as $index => $nok) {
            $client->nextOfKin()->create([
                'first_name' => $this->normalize($nok['first_name'] ?? null),
                'middle_initial' => $this->normalize($nok['middle_initial'] ?? null),
                'last_name' => $this->normalize($nok['last_name'] ?? null),
                'relationship' => $this->normalize($nok['relationship'] ?? null),
                'phone_number' => $this->normalize($nok['phone_number'] ?? null),
                'email' => $this->normalize($nok['email'] ?? null),
                'region' => $this->normalize($nok['region'] ?? null),
                'province' => $this->normalize($nok['province'] ?? null),
                'city_municipality' => $this->normalize($nok['city_municipality'] ?? null),
                'barangay' => $this->normalize($nok['barangay'] ?? null),
                'street' => $this->normalize($nok['street'] ?? null),
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }

        $changes['Next of kin'] = [
            'old' => null,
            'new' => empty($nokList) ? 'Removed' : 'Updated',
        ];
    }

    private function notifyCaseManagers(Client $client, User $ofwUser, array $changes): void
    {
        $notifiedUserIds = [];

        $client->caseFiles()
            ->where('is_deleted', false)
            ->whereNotNull('user_id')
            ->get()
            ->each(function ($case) use ($ofwUser, $changes, &$notifiedUserIds) {
                if (isset($notifiedUserIds[$case->user_id])) {
                    return;
                }

                $manager = User::find($case->user_id);
                if (! $manager) {
                    return;
                }

                $this->notificationService->notifyUsers(
                    [$manager],
                    new CaseUpdated($case, $ofwUser->name, $changes),
                );

                $notifiedUserIds[$case->user_id] = true;
            });
    }

    private function diffFields($row, array $source, array $fields): array
    {
        $dirty = [];

        foreach ($fields as $field) {
            $oldValue = $row->{$field};
            if ($oldValue instanceof DateTimeInterface) {
                $oldValue = $oldValue->toDateString();
            }

            $newValue = $this->normalize($source[$field] ?? null);

            if ((string) ($oldValue ?? '') !== (string) ($newValue ?? '')) {
                $dirty[$field] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        return $dirty;
    }

    private function pickFields(array $source, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = $this->normalize($source[$field] ?? null);
        }

        return $result;
    }

    private function isEmptyFields(array $source, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! empty($source[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }
}
