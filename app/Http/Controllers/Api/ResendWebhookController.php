<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailEvent;
use App\Models\EmailLog;
use App\Services\Mail\DeliveryStatus;
use App\Services\Mail\SvixWebhookVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Receives Resend delivery webhooks and reconciles them with email_logs.
 *
 * Without this, email_logs only records that the provider accepted an API call.
 * A message that later bounced, was suppressed, or was marked as spam would be
 * indistinguishable from one that reached the inbox — which makes "the user
 * never received their OTP" undiagnosable from inside the application.
 *
 * Declared in routes/api.php rather than web.php on purpose: the web group
 * appends session, CSRF, MFA, and Inertia middleware, all meaningless for a
 * server-to-server callback and each a way to reject a legitimate webhook.
 */
class ResendWebhookController extends Controller
{
    /** PostgreSQL SQLSTATE for unique_violation. */
    private const UNIQUE_VIOLATION = '23505';

    public function __invoke(Request $request, SvixWebhookVerifier $verifier): JsonResponse
    {
        if (! $verifier->verify($request)) {
            Log::warning('Rejected Resend webhook: signature verification failed', [
                'svix_id' => $request->header('svix-id'),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();

        if (! is_array($payload)) {
            return response()->json(['message' => 'Malformed webhook payload.'], 422);
        }

        $eventType = $payload['type'] ?? null;

        if (! is_string($eventType) || $eventType === '') {
            return response()->json(['message' => 'Malformed webhook payload.'], 422);
        }

        // Unrecognised event types return 200 rather than 4xx. The provider
        // retries 4xx responses, and a request that can never succeed would be
        // retried until the endpoint is disabled provider-side.
        if (! DeliveryStatus::isRecorded($eventType)) {
            return response()->json(['message' => 'Event type ignored.'], 200);
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $emailId = $data['email_id'] ?? null;

        if (! is_string($emailId) || $emailId === '') {
            return response()->json(['message' => 'Missing data.email_id.'], 422);
        }

        $svixId = (string) $request->header('svix-id');

        try {
            DB::transaction(function () use ($svixId, $eventType, $emailId, $payload) {
                $log = EmailLog::where('provider_message_id', $emailId)->first();

                EmailEvent::create([
                    'email_log_id' => $log?->id,
                    'provider_message_id' => $emailId,
                    'event_type' => $eventType,
                    'occurred_at' => $this->occurredAt($payload),
                    'svix_id' => $svixId,
                    'payload' => $payload,
                ]);

                if ($log !== null) {
                    $this->applyToLog($log, $eventType, $payload);
                }
            });
        } catch (QueryException $e) {
            // Svix reuses svix-id across retries, so the unique constraint is
            // what makes replay idempotent. Report success: the event is stored.
            if ($this->isUniqueViolation($e)) {
                return response()->json(['message' => 'Event already processed.'], 200);
            }

            throw $e;
        }

        $this->logProblemEvents($eventType, $emailId, $data);

        return response()->json(['message' => 'Processed.'], 200);
    }

    /**
     * Advance the log row's delivery state, if this event outranks it.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyToLog(EmailLog $log, string $eventType, array $payload): void
    {
        $attributes = [];
        $status = DeliveryStatus::fromEvent($eventType);

        if ($status !== null && DeliveryStatus::outranks($status, $log->status)) {
            $attributes['status'] = $status;

            $reason = $this->extractReason($payload);

            if ($reason !== null) {
                $attributes['error_message'] = $reason;
            }
        }

        // Recorded independently of the status advance so a delivery timestamp
        // is still captured on a message that later bounced.
        if ($eventType === 'email.delivered' && $log->delivered_at === null) {
            $attributes['delivered_at'] = $this->occurredAt($payload) ?? now();
        }

        if ($attributes !== []) {
            $log->update($attributes);
        }
    }

    /**
     * Human-readable reason a message did not arrive, for the admin screen.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractReason(array $payload): ?string
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $bounce = $data['bounce'] ?? null;

        if (is_array($bounce)) {
            $parts = array_filter(
                [$bounce['type'] ?? null, $bounce['subType'] ?? null, $bounce['message'] ?? null],
                fn ($part) => is_string($part) && $part !== ''
            );

            if ($parts !== []) {
                return Str::limit(implode(': ', $parts), 1000, '');
            }
        }

        $failed = $data['failed'] ?? null;

        if (is_array($failed) && is_string($failed['reason'] ?? null) && $failed['reason'] !== '') {
            return Str::limit($failed['reason'], 1000, '');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function occurredAt(array $payload): ?CarbonImmutable
    {
        $timestamp = $payload['created_at'] ?? null;

        if (! is_string($timestamp) || $timestamp === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($timestamp);
        } catch (Throwable) {
            // A provider timestamp we cannot parse must not reject the event.
            return null;
        }
    }

    /**
     * Surface delivery failures in the application log so they are visible
     * without opening the provider dashboard.
     *
     * @param  array<string, mixed>  $data
     */
    private function logProblemEvents(string $eventType, string $emailId, array $data): void
    {
        $status = DeliveryStatus::fromEvent($eventType);

        if ($status === null || ! in_array($status, DeliveryStatus::PROBLEM_STATUSES, true)) {
            return;
        }

        Log::warning('Mail delivery problem reported by provider', [
            'event_type' => $eventType,
            'provider_message_id' => $emailId,
            'to' => $data['to'] ?? null,
            'subject' => $data['subject'] ?? null,
        ]);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === self::UNIQUE_VIOLATION
            || (string) $e->getCode() === self::UNIQUE_VIOLATION;
    }
}
