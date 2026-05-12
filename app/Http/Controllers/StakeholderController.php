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

    public function show(Agency $stakeholder)
    {
        $stakeholder->load([
            'services.requirements',
            'referrals' => function ($q) {
                $q->select('id', 'agcy_id', 'status');
            },
        ]);

        $referrals = $stakeholder->referrals;
        $activeReferrals = $referrals->whereIn('status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'])->count();
        $completedReferrals = $referrals->where('status', 'COMPLETED')->count();

        return Inertia::render('Stakeholder/Show', [
            'stakeholder' => $stakeholder->toArray(),
            'stats' => [
                'total_referrals' => $referrals->count(),
                'active_referrals' => $activeReferrals,
                'completed_referrals' => $completedReferrals,
            ],
        ]);
    }
}
