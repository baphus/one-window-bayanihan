<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Accept CSP violation reports sent by the browser.
 *
 * Accept both legacy report-uri payloads and Reporting API report-to batches.
 */
class CspViolationController extends Controller
{
    public function report(Request $request): JsonResponse
    {
        // application/csp-report is not recognized as JSON by Request::isJson().
        // Decode explicitly so legacy browsers and application/reports+json work.
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return response()->json(null, 204);
        }

        if (array_is_list($payload)) {
            foreach (array_slice($payload, 0, 20) as $report) {
                if (! is_array($report) || ($report['type'] ?? null) !== 'csp-violation' || ! is_array($report['body'] ?? null)) {
                    continue;
                }

                $body = $report['body'];
                $this->logViolation($request, [
                    'blocked-uri' => $body['blockedURL'] ?? null,
                    'effective-directive' => $body['effectiveDirective'] ?? null,
                    'original-policy' => $body['originalPolicy'] ?? null,
                    'document-uri' => $body['documentURL'] ?? null,
                    'referrer' => $body['referrer'] ?? null,
                    'source-file' => $body['sourceFile'] ?? null,
                    'line-number' => $body['lineNumber'] ?? null,
                    'column-number' => $body['columnNumber'] ?? null,
                ]);
            }
        } elseif (is_array($payload['csp-report'] ?? null)) {
            $this->logViolation($request, $payload['csp-report']);
        }

        return response()->json(null, 204);
    }

    private function logViolation(Request $request, array $violation): void
    {
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
    }
}
