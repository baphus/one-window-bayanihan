<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Inertia\Inertia;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Feedback::with(['agency', 'caseFile.client', 'servqualResponses']);

        if ($user->role === 'AGENCY' && $user->agcy_id) {
            $query->where('agency_id', $user->agcy_id);
        }

        $feedbacks = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Feedback/Index', [
            'feedbacks' => $feedbacks,
        ]);
    }
}
