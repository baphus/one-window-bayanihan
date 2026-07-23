<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ReferralOverdueMail;
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
        $user = $request->user();
        $overdueDays = (int) SystemSetting::getValue('referral_overdue_days', 7);

        $dashboardData = $this->referralService->getOverdueReferralsDashboard(
            userRole: $user->role,
            userId: $user->id,
            userAgencyId: $user->agcy_id,
            overdueDays: $overdueDays,
            filters: $request->only(['sort_by', 'status_filter', 'per_page']),
        );

        return Inertia::render('Admin/OverdueReferrals/Index', $dashboardData + [
            'userRole' => $user->role,
            'overdueDays' => $overdueDays,
        ]);
    }

    public function sendReminders(Request $request)
    {
        $user = $request->user();
        abort_if($user->role === 'AGENCY', 403);

        $validated = $request->validate([
            'referral_ids' => ['sometimes', 'array', 'max:500'],
            'referral_ids.*' => ['bail', 'uuid', 'distinct'],
            'status_filter' => ['sometimes', 'nullable', 'string', 'in:all,pending,processing,for_compliance,PENDING,PROCESSING,FOR_COMPLIANCE'],
        ]);
        $overdueDays = (int) SystemSetting::getValue('referral_overdue_days', 7);

        // An empty selection deliberately means the complete display set.
        // The service applies the same inactivity and role scope in both cases.
        $referrals = $this->referralService->getOverdueReferralsForReminders(
            userRole: $user->role,
            userId: $user->id,
            userAgencyId: $user->agcy_id,
            overdueDays: $overdueDays,
            filters: ['status_filter' => $validated['status_filter'] ?? 'all'],
            referralIds: $validated['referral_ids'] ?? [],
        );

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
