<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientSelectController extends Controller
{
    public function search(Request $request)
    {
        $query = Client::with('caseFile')
            ->where('is_deleted', false);

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%");
            });
        }

        $clients = $query->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($client) => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'middle_name' => $client->middle_name,
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
            'middle_name' => $client->middle_name,
            'suffix' => $client->suffix,
            'sex' => $client->sex,
            'date_of_birth' => $client->date_of_birth?->format('Y-m-d'),
            'email' => $client->email,
            'contact_number' => $client->contact_number,
            'avatar_url' => $client->avatar_url,
            'addresses' => $client->addresses->map(fn ($a) => [
                'id' => $a->id,
                'region' => $a->region,
                'province' => $a->province,
                'city_municipality' => $a->city_municipality,
                'barangay' => $a->barangay,
                'street' => $a->street,
            ]),
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
            ]),
            'case_file' => $client->caseFile ? [
                'case_number' => $client->caseFile->case_number,
            ] : null,
        ]]);
    }
}
