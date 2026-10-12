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
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        // publishedOnly: this feeds the "select existing client" picker
        // on the case-creation form. Only linked accounts holding a live OPEN
        // case are selectable.
        $query = Client::withCount('caseFiles')
            ->where('is_deleted', false)
            ->publishedOnly();

        if ($search = $validated['q'] ?? null) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('middle_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $clients = $query->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($client) => $this->clientSummary($client));

        return response()->json(['data' => $clients]);
    }

    /**
     * Duplicate-email probe for the case-creation form. Returns the first
     * non-deleted, searchable client using the same verified address, so the
     * form can offer "link this existing client instead" without a second
     * name-based search. Mirrors IntakeService's email join semantics.
     */
    public function checkEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => ['nullable', 'string', 'max:255'],
        ]);

        $email = $validated['email'] ?? null;

        if ($email === null || trim($email) === '') {
            return response()->json(['duplicate' => false, 'client' => null]);
        }

        $client = Client::withCount('caseFiles')
            ->where('is_deleted', false)
            ->publishedOnly()
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])
            ->first();

        if (! $client) {
            return response()->json(['duplicate' => false, 'client' => null]);
        }

        return response()->json([
            'duplicate' => true,
            'client' => $this->clientSummary($client),
        ]);
    }

    /**
     * Scoped NOK dropdown: OFW clients linked via ofw_client_id to the
     * filer's own client. Minimal fields only — no broad PII search.
     */
    public function linkedOfws(Request $request)
    {
        $filer = $request->user();

        if (! $filer->client) {
            return response()->json(['data' => []]);
        }

        $clients = Client::where('is_deleted', false)
            ->where('type', 'OFW')
            ->where('ofw_client_id', $filer->client->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(50)
            ->get()
            ->map(fn ($client) => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'middle_name' => $client->middle_name,
                'last_name' => $client->last_name,
                'suffix' => $client->suffix,
                'email' => $client->email,
            ]);

        return response()->json(['data' => $clients]);
    }

    private function clientSummary(Client $client): array
    {
        return [
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
            'case_files_count' => (int) $client->case_files_count,
            'case_file' => $client->caseFile ? [
                'case_number' => $client->caseFile->case_number,
            ] : null,
        ];
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

        // Hiding the client from the picker is not enough on its own: this route
        // returns the filer's full PII for any id, so it has to refuse the same
        // records rather than rely on nobody holding the uuid.
        abort_if(! Client::whereKey($client->id)->publishedOnly()->exists(), 404);

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
                'middle_name' => $n->middle_name,
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
