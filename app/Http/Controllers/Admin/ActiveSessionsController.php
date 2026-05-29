<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SessionService;
use Inertia\Inertia;

class ActiveSessionsController extends Controller
{
    public function index(SessionService $service)
    {
        return Inertia::render('Admin/ActiveSessions/Index', [
            'sessions' => $service->getSessions(),
        ]);
    }

    public function terminate(string $session, SessionService $service)
    {
        if (session()->getId() === $session) {
            return back()->with('error', 'You cannot terminate your current session.');
        }

        $service->terminate($session);

        return back()->with('success', 'Session terminated.');
    }
}
