<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatbotMessageRequest;
use App\Services\Chatbot\ChatbotAudience;
use App\Services\Chatbot\ChatbotConversationService;
use Illuminate\Http\JsonResponse;

class ChatbotController extends Controller
{
    public function message(ChatbotMessageRequest $request, ChatbotConversationService $conversation): JsonResponse
    {
        return response()->json($conversation->reply($request->validated(), ChatbotAudience::forUser($request->user())));
    }
}
