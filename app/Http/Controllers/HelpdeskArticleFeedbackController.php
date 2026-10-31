<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHelpdeskArticleFeedbackRequest;
use App\Services\HelpdeskArticleService;
use App\Services\HelpdeskFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HelpdeskArticleFeedbackController extends Controller
{
    public function index(HelpdeskArticleService $articles, HelpdeskFeedbackService $feedback): Response
    {
        return Inertia::render('Admin/HelpdeskFeedback/Index', ['articles' => $feedback->summary($articles)]);
    }

    public function show(Request $request, string $slug, HelpdeskArticleService $articles, HelpdeskFeedbackService $feedback): JsonResponse
    {
        abort_unless($articles->findAllowed($slug, $request->user()) !== null, 404);
        $identity = $feedback->identity($request, true);

        return response()->json(['vote' => $feedback->currentVote($slug, $identity)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreHelpdeskArticleFeedbackRequest $request, string $slug, HelpdeskArticleService $articles, HelpdeskFeedbackService $feedback): JsonResponse
    {
        abort_unless($articles->findAllowed($slug, $request->user()) !== null, 404);
        $identity = $feedback->identity($request);
        abort_if($identity === null, 409, 'Initialize feedback before voting.');
        $vote = $request->validated('vote');
        $feedback->store($slug, $identity, $vote);

        return response()->json(['vote' => $vote])->header('Cache-Control', 'private, no-store');
    }
}
