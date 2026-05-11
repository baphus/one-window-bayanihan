<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CaseFile;
use Inertia\Inertia;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::with(['caseFile' => function ($q) {
            $q->with('referrals.agency');
        }])->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Client/Index', [
            'clients' => $clients,
        ]);
    }

    public function show(string $id)
    {
        $client = Client::with([
            'caseFile' => function ($q) {
                $q->with(['referrals.agency', 'referrals.milestones', 'user']);
            },
            'addresses',
            'employments',
        ])->findOrFail($id);

        return Inertia::render('Client/Show', [
            'client' => $client,
        ]);
    }
}
