<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePendingRegistration
{
    public function handle(Request $request, Closure $next): Response
    {
        $pending = $request->session()->get('pending_registration');

        // OTP is verified in storeReview before the tx; the gate only checks
        // step-1 credentials exist so a verified flag reset never strands users.
        if (! is_array($pending) || empty($pending['credentials'])) {
            return redirect()->route('register');
        }

        return $next($request);
    }
}
