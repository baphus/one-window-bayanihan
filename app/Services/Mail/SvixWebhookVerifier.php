<?php

namespace App\Services\Mail;

use Illuminate\Http\Request;

/**
 * Verifies Svix-signed webhook requests (the scheme Resend uses).
 *
 * The signed content is "{svix-id}.{svix-timestamp}.{raw body}", HMAC-SHA256'd
 * with the base64-decoded portion of the signing secret after the "whsec_"
 * prefix, then base64 encoded.
 *
 * This is implemented in-repo rather than via the Svix SDK: the algorithm is
 * short and fully specified, and this sits in the request path of an
 * authentication-critical subsystem where a dependency is not worth the risk.
 */
class SvixWebhookVerifier
{
    /**
     * How far the provider's timestamp may drift from ours before the request is
     * treated as a replay.
     */
    private const TOLERANCE_SECONDS = 300;

    private ?string $secret;

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?? config('services.resend.webhook_secret');
    }

    /**
     * Whether this request carries a valid signature for the configured secret.
     *
     * Returns false when no secret is configured. The endpoint must fail closed:
     * an unauthenticated writer to email_logs would be a spoofing vector.
     */
    public function verify(Request $request): bool
    {
        if (blank($this->secret)) {
            return false;
        }

        $id = $request->header('svix-id');
        $timestamp = $request->header('svix-timestamp');
        $signatureHeader = $request->header('svix-signature');

        if (blank($id) || blank($timestamp) || blank($signatureHeader)) {
            return false;
        }

        if (! $this->timestampIsFresh($timestamp)) {
            return false;
        }

        $key = $this->decodeSecret();

        if ($key === null) {
            return false;
        }

        // The raw body is required — re-encoding the decoded JSON would change
        // whitespace and key order and invalidate the signature.
        $signedContent = $id.'.'.$timestamp.'.'.$request->getContent();
        $expected = base64_encode(hash_hmac('sha256', $signedContent, $key, true));

        return $this->matchesAnySignature($signatureHeader, $expected);
    }

    /**
     * The header holds space-delimited "v1,<signature>" pairs. More than one is
     * present during secret rotation, so every v1 entry must be considered —
     * checking only the first would break rotation.
     */
    private function matchesAnySignature(string $header, string $expected): bool
    {
        $matched = false;

        foreach (explode(' ', $header) as $entry) {
            $parts = explode(',', trim($entry), 2);

            if (count($parts) !== 2 || $parts[0] !== 'v1' || $parts[1] === '') {
                continue;
            }

            // No early return: compare every candidate so the work done does not
            // depend on which entry matched.
            if (hash_equals($expected, $parts[1])) {
                $matched = true;
            }
        }

        return $matched;
    }

    private function timestampIsFresh(string $timestamp): bool
    {
        if (! is_numeric($timestamp)) {
            return false;
        }

        return abs(now()->getTimestamp() - (int) $timestamp) <= self::TOLERANCE_SECONDS;
    }

    /**
     * Decode the signing key, tolerating a secret given with or without the
     * "whsec_" prefix.
     */
    private function decodeSecret(): ?string
    {
        $secret = $this->secret;

        if (str_starts_with($secret, 'whsec_')) {
            $secret = substr($secret, strlen('whsec_'));
        }

        $decoded = base64_decode($secret, true);

        return ($decoded === false || $decoded === '') ? null : $decoded;
    }
}
