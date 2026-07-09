<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Accept CSP violation reports sent by the browser in Report-Only mode.
 *
 * Logs each violation at DEBUG level for monitoring during the evaluation
 * phase before switching to enforced mode.
 */
class CspViolationController extends Controller
{
    public function report(Request $request): JsonResponse
    {
        $violation = $request->input('csp-report', $request->all());

        Log::debug('CSP violation reported', [
            'blocked_uri' => $violation['blocked-uri'] ?? null,
            'violated_directive' => $violation['violated-directive'] ?? null,
            'effective_directive' => $violation['effective-directive'] ?? null,
            'original_policy' => $violation['original-policy'] ?? null,
            'document_uri' => $violation['document-uri'] ?? null,
            'referrer' => $violation['referrer'] ?? null,
            'source_file' => $violation['source-file'] ?? null,
            'line_number' => $violation['line-number'] ?? null,
            'column_number' => $violation['column-number'] ?? null,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);

        return response()->json(null, 204);
    }
}
