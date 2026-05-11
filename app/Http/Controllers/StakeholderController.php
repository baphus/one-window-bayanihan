<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use Inertia\Inertia;

class StakeholderController extends Controller
{
    public function index()
    {
        $agencies = Agency::with(['services', 'referrals' => function ($q) {
            $q->select('id', 'agcy_id', 'status');
        }])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->toArray();

        return Inertia::render('Stakeholder/Index', [
            'agencies' => $agencies,
        ]);
    }
}
