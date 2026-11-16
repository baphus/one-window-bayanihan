<?php

namespace App\Http\Controllers;

use App\Rules\VulnerabilityRule;
use App\Services\ClientDirectoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClientDirectoryController extends Controller
{
    public function index(Request $request, ClientDirectoryService $directory)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['Any', 'Open', 'Closed', 'Both'])],
            'client_type' => ['nullable', Rule::in(['OFW', 'NEXT_OF_KIN'])],
            'vulnerability_indicator' => ['nullable', 'array'],
            'vulnerability_indicator.*' => ['string', Rule::in(VulnerabilityRule::VALUES)],
            'sort' => ['nullable', Rule::in(['name', 'latest_case_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ]);

        return Inertia::render('Clients/Index', $directory->index([
            'search' => trim($filters['search'] ?? ''),
            'status' => $filters['status'] ?? 'Any',
            'client_type' => $filters['client_type'] ?? null,
            'vulnerability_indicator' => $filters['vulnerability_indicator'] ?? [],
            'sort' => $filters['sort'] ?? 'latest_case_at',
            'direction' => $filters['direction'] ?? 'desc',
            'per_page' => (int) ($filters['per_page'] ?? 15),
        ]));
    }

    public function show(string $kind, string $id, ClientDirectoryService $directory)
    {
        return Inertia::render('Clients/Show', $directory->show($kind, $id));
    }
}
