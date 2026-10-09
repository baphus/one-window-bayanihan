<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferralMessageRequest;
use App\Models\Referral;
use App\Services\ReferralMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralMessageController extends Controller
{
    public function __construct(
        private readonly ReferralMessageService $service,
    ) {}

    /**
     * JSON-only thread endpoint consumed by the referral page modal. Direct
     * browser navigation lands on the referral page instead of a JSON dump.
     */
    public function index(Request $request, Referral $referral)
    {
        if (! $request->wantsJson() && ! $request->isJson()) {
            return redirect()->route('referrals.show', $referral);
        }

        return response()->json(['data' => $this->service->listMessages($referral, $request->user())]);
    }

    public function store(StoreReferralMessageRequest $request, Referral $referral): JsonResponse
    {
        $message = $this->service->sendMessage($referral, $request->user(), $request->validated('body'));

        return response()->json(['data' => $message], 201);
    }

    public function markRead(Request $request, Referral $referral): JsonResponse
    {
        $this->service->markThreadRead($referral, $request->user());

        return response()->json(['unread_count' => $this->service->getUnreadCount($referral, $request->user())]);
    }
}
