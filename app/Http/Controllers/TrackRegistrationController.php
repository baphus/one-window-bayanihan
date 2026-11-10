<?php

namespace App\Http\Controllers;

class TrackRegistrationController extends Controller
{
    public function store()
    {
        // Tracking verification never grants account ownership of a case.
        return response()->json([
            'success' => true,
            'redirect' => route('register'),
        ]);
    }
}
