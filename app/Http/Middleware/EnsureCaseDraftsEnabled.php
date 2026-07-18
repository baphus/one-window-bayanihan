<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCaseDraftsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('features.case_drafts.enabled')) {
            return response()->json([
                'message' => 'Case drafts are currently unavailable.',
                'code' => 'CASE_DRAFTS_DISABLED',
            ], 503);
        }

        return $next($request);
    }
}
