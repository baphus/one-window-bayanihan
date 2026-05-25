<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ReferralOverdueMail;
use App\Models\Referral;
use App\Models\SystemSetting;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class OverdueReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {}

    public function index(Request $request)
    {
        $overdueDays = (int) SystemSetting::getValue('referral_overdue_days', 7);
        $overdueReferrals = $this->referralService->getOverdueReferrals(
            $overdueDays,
            $request->user()->agcy_id,
            $request->user()->role,
        );

        return Inertia::render('Admin/OverdueReferrals/Index', [
            'overdueReferrals' => $overdueReferrals,
            'overdueDays' => $overdueDays,
        ]);
    }

    public function sendReminders(Request $request)
    {
        $overdueDays = (int) SystemSetting::getValue('referral_overdue_days', 7);
        $cutoff = now()->subDays($overdueDays);

        $query = Referral::with([
            'caseFile.client',
            'agency.users' => fn ($q) => $q->where('role', 'AGENCY')->where('is_active', true),
        ])
            ->whereNotIn('status', ['COMPLETED', 'REJECTED'])
            ->where('created_at', '<', $cutoff);

        if ($request->user()->role === 'AGENCY' && $request->user()->agcy_id) {
            $query->where('agcy_id', $request->user()->agcy_id);
        }

        $referralIds = $request->input('referral_ids', []);
        if (! empty($referralIds)) {
            $query->whereIn('id', $referralIds);
        }

        $referrals = $query->get();

        $sentCount = 0;
        foreach ($referrals as $referral) {
            $handlers = $referral->agency?->users ?? collect();
            foreach ($handlers as $handler) {
                Mail::to($handler->email, $handler->name)
                    ->queue(new ReferralOverdueMail($referral, $overdueDays));
                $sentCount++;
            }
        }

        $message = $sentCount
            ? "{$sentCount} reminder email(s) queued successfully."
            : 'No overdue referrals found to notify.';

        return redirect()->route('overdue-referrals.index')
            ->with('success', $message);
    }
}
