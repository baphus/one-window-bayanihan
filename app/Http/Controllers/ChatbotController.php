<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatbotMessageRequest;
use App\Services\Chatbot\ChatbotAudience;
use App\Services\Chatbot\ChatbotConversationService;
use App\Services\Chatbot\ChatbotDailyQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function message(ChatbotMessageRequest $request, ChatbotConversationService $conversation, ChatbotDailyQuota $dailyQuota): JsonResponse
    {
        [$identity, $cookie] = $this->identity($request, $dailyQuota);
        $admission = $dailyQuota->admit($identity);
        if (! $admission['admitted']) {
            $response = response()->json(['status' => 'quota_exceeded', 'message' => 'Daily assistant message limit reached. Try again after reset.', 'quota' => $admission['quota']], 429)
                ->header('Retry-After', (string) max(1, (int) ceil(now('Asia/Manila')->diffInSeconds($admission['quota']['resets_at']))))
                ->header('Cache-Control', 'no-store');
        } else {
            $response = response()->json([...$conversation->reply($request->validated(), ChatbotAudience::forUser($request->user())), 'quota' => $admission['quota']]);
        }

        return $cookie ? $response->withCookie($cookie) : $response;
    }

    public function quota(Request $request, ChatbotDailyQuota $dailyQuota): JsonResponse
    {
        [$identity, $cookie] = $this->identity($request, $dailyQuota);
        $response = response()->json(['quota' => $dailyQuota->quota($identity)])->header('Cache-Control', 'no-store');

        return $cookie ? $response->withCookie($cookie) : $response;
    }

    private function identity(Request $request, ChatbotDailyQuota $dailyQuota): array
    {
        $guestId = $request->attributes->get('chatbot_guest_id') ?? $request->cookie('chatbot_guest');
        if (! $request->user() && (! is_string($guestId) || strlen($guestId) !== 64 || ! ctype_xdigit($guestId))) {
            $guestId = bin2hex(random_bytes(32));
            $request->attributes->set('chatbot_guest_id', $guestId);
            $cookie = cookie('chatbot_guest', $guestId, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax');
        } else {
            $cookie = null;
        }

        return [$dailyQuota->identity($request->user()?->getAuthIdentifier(), $guestId), $cookie];
    }
}
