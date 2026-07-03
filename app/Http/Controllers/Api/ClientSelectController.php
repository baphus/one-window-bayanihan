<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\PhilippineAddressService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientSelectController extends Controller
{
    public function __construct(
        private readonly PhilippineAddressService $addressService,
    ) {}

    public function search(Request $request)
    {
        $query = Client::with('caseFile')
            ->where('is_deleted', false);

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('middle_initial', 'ilike', "%{$search}%");
            });
        }

        $clients = $query->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($client) => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'middle_initial' => $client->middle_initial,
                'suffix' => $client->suffix,
                'sex' => $client->sex,
                'date_of_birth' => $client->date_of_birth?->format('Y-m-d'),
                'avatar_url' => $client->avatar_url,
                'case_file' => $client->caseFile ? [
                    'case_number' => $client->caseFile->case_number,
                ] : null,
            ]);

        return response()->json(['data' => $clients]);
    }

    public function show(string $id)
    {
        abort_unless(Str::isUuid($id), 404);

        $client = Client::with([
            'addresses',
            'employments',
            'nextOfKin',
            'caseFile',
        ])->findOrFail($id);

        return response()->json(['data' => [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'middle_initial' => $client->middle_initial,
            'suffix' => $client->suffix,
            'sex' => $client->sex,
            'date_of_birth' => $client->date_of_birth?->format('Y-m-d'),
            'email' => $client->email,
            'contact_number' => $client->contact_number,
            'avatar_url' => $client->avatar_url,
            'addresses' => $client->addresses->map(function ($a) {
                $codes = $this->addressService->resolveAddressToCodes([
                    'region' => $a->region,
                    'province' => $a->province,
                    'city_municipality' => $a->city_municipality,
                    'barangay' => $a->barangay,
                ]);

                return [
                    'id' => $a->id,
                    'region' => $codes['region'] ?? $a->region,
                    'province' => $codes['province'] ?? $a->province,
                    'city_municipality' => $codes['city_municipality'] ?? $a->city_municipality,
                    'barangay' => $codes['barangay'] ?? $a->barangay,
                    'street' => $a->street,
                    'region_name' => $a->region,
                    'province_name' => $a->province,
                    'city_municipality_name' => $a->city_municipality,
                    'barangay_name' => $a->barangay,
                ];
            }),
            'employments' => $client->employments->map(fn ($e) => [
                'id' => $e->id,
                'employer_name' => $e->employer_name,
                'position' => $e->position,
                'country' => $e->country,
                'last_country' => $e->last_country,
                'last_position' => $e->last_position,
                'date_of_arrival' => $e->date_of_arrival?->format('Y-m-d'),
            ]),
            'nextOfKin' => $client->nextOfKin->map(fn ($n) => [
                'id' => $n->id,
                'first_name' => $n->first_name,
                'middle_initial' => $n->middle_initial,
                'last_name' => $n->last_name,
                'relationship' => $n->relationship,
                'phone_number' => $n->phone_number,
                'email' => $n->email,
                'full_address' => $n->full_address,
                'region' => $n->region,
                'province' => $n->province,
                'city_municipality' => $n->city_municipality,
                'barangay' => $n->barangay,
                'street' => $n->street,
                'is_primary' => $n->is_primary,
                'sort_order' => $n->sort_order,
            ]),
            'case_file' => $client->caseFile ? [
                'case_number' => $client->caseFile->case_number,
            ] : null,
        ]]);
    }
}
