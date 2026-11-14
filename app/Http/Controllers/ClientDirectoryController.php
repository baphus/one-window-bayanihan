<?php

namespace App\Http\Controllers;

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
        ]);

        return Inertia::render('Clients/Index', $directory->index([
            'search' => trim($filters['search'] ?? ''),
            'status' => $filters['status'] ?? 'Any',
        ]));
    }

    public function show(string $kind, string $id, ClientDirectoryService $directory)
    {
        return Inertia::render('Clients/Show', $directory->show($kind, $id));
    }
}
