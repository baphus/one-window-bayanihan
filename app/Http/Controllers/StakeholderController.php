<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Models\Agency;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StakeholderController extends Controller
{
    public function index()
    {
        $agencies = CacheHelper::safeRemember('stakeholder:agencies_list', 600, function () {
            return Agency::with(['services'])
                ->withCount([
                    'referrals as total_referrals_count',
                    'referrals as active_referrals_count' => fn ($q) => $q->whereIn('status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE']),
                    'referrals as completed_referrals_count' => fn ($q) => $q->where('status', 'COMPLETED'),
                ])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->toArray();
        });

        return Inertia::render('Stakeholder/Index', [
            'agencies' => $agencies,
        ]);
    }

    public function show(Request $request, Agency $stakeholder)
    {
        $user = $request->user();
        if ($user->isAgency() && $stakeholder->id !== $user->agcy_id) {
            abort(404, 'Stakeholder not found.');
        }

        $agencyData = CacheHelper::safeRemember('stakeholder:agency:' . $stakeholder->id, 300, function () use ($stakeholder) {
            $stakeholder->load(['services.requirements']);
            $stakeholder->loadCount([
                'referrals as total_referrals_count',
                'referrals as active_referrals_count' => fn ($q) => $q->whereIn('status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE']),
                'referrals as completed_referrals_count' => fn ($q) => $q->where('status', 'COMPLETED'),
            ]);

            return [
                'stakeholder' => $stakeholder->toArray(),
                'stats' => [
                    'total_referrals' => (int) $stakeholder->total_referrals_count,
                    'active_referrals' => (int) $stakeholder->active_referrals_count,
                    'completed_referrals' => (int) $stakeholder->completed_referrals_count,
                ],
            ];
        });

        return Inertia::render('Stakeholder/Show', $agencyData);
    }
}
