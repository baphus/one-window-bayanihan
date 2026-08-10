<?php

namespace App\Http\Controllers;

use App\Services\OfwProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class OfwProfileController extends Controller
{
    public function __construct(
        private readonly OfwProfileService $profileService,
    ) {}

    /**
     * Show the OFW profile edit page.
     *
     * Identity fields (name, date of birth, sex, email) are staff-owned and
     * passed read-only. The drift-prone sections the OFW can edit themselves
     * (contact number, address, employment, next of kin) are passed as editable
     * payloads.
     */
    public function edit()
    {
        $user = request()->user();

        return Inertia::render('OFW/Profile', [
            'user' => $user->only(['id', 'name', 'email', 'contact_number']),
            'client' => $this->clientPayload($user),
        ]);
    }

    /**
     * Update whichever self-service profile section was submitted.
     *
     * Each section is saved independently (one save button per block), so only
     * the fields present in the payload are touched. The password change is the
     * exception: it requires the current password and only runs when a new
     * password was provided.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => [
                'nullable',
                Rule::requiredIf(fn () => filled($request->input('password'))),
                Rule::when(fn () => filled($request->input('password')), ['current_password']),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'array'],
            'address.region' => ['nullable', 'string', 'max:255'],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.city_municipality' => ['nullable', 'string', 'max:255'],
            'address.barangay' => ['nullable', 'string', 'max:255'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'employment' => ['sometimes', 'array'],
            'employment.employer_name' => ['nullable', 'string', 'max:255'],
            'employment.position' => ['nullable', 'string', 'max:255'],
            'employment.country' => ['nullable', 'string', 'max:255'],
            'employment.start_date' => ['nullable', 'date'],
            'employment.end_date' => ['nullable', 'date'],
            'employment.last_country' => ['nullable', 'string', 'max:255'],
            'employment.last_position' => ['nullable', 'string', 'max:255'],
            'employment.date_of_arrival' => ['nullable', 'date'],
            'next_of_kin' => ['sometimes', 'array'],
            'next_of_kin.*.first_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.middle_initial' => ['nullable', 'string', 'max:10'],
            'next_of_kin.*.last_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.relationship' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.phone_number' => ['nullable', 'string', 'max:20'],
            'next_of_kin.*.email' => ['nullable', 'email', 'max:255'],
            'next_of_kin.*.region' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.province' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.city_municipality' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.barangay' => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.street' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->save();
        }

        $this->profileService->updateClientProfile($user, $validated);

        return redirect()->route('ofw.profile.edit')->with('success', 'Profile updated successfully.');
    }

    private function clientPayload($user): ?array
    {
        $client = $user->client;

        if (! $client) {
            return null;
        }

        $address = $client->addresses()->orderBy('created_at')->first();
        $employment = $client->employments()->orderBy('created_at')->first();
        $nextOfKin = $client->nextOfKin()
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($nok) => [
                'id' => $nok->id,
                'first_name' => $nok->first_name,
                'middle_initial' => $nok->middle_initial,
                'last_name' => $nok->last_name,
                'relationship' => $nok->relationship,
                'phone_number' => $nok->phone_number,
                'email' => $nok->email,
                'region' => $nok->region,
                'province' => $nok->province,
                'city_municipality' => $nok->city_municipality,
                'barangay' => $nok->barangay,
                'street' => $nok->street,
            ])
            ->values();

        return [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'middle_initial' => $client->middle_initial,
            'last_name' => $client->last_name,
            'suffix' => $client->suffix,
            'sex' => $client->sex,
            'date_of_birth' => $client->date_of_birth?->toDateString(),
            'email' => $client->email,
            'contact_number' => $client->contact_number,
            'avatar_url' => $client->avatar_url,
            'address' => $address ? [
                'region' => $address->region,
                'province' => $address->province,
                'city_municipality' => $address->city_municipality,
                'barangay' => $address->barangay,
                'street' => $address->street,
            ] : null,
            'employment' => $employment ? [
                'employer_name' => $employment->employer_name,
                'position' => $employment->position,
                'country' => $employment->country,
                'start_date' => $employment->start_date?->toDateString(),
                'end_date' => $employment->end_date?->toDateString(),
                'last_country' => $employment->last_country,
                'last_position' => $employment->last_position,
                'date_of_arrival' => $employment->date_of_arrival?->toDateString(),
            ] : null,
            'next_of_kin' => $nextOfKin,
        ];
    }
}
