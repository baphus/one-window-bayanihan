<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Models\Referral;
use App\Models\Service;
use App\Models\ServqualConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FeedbackInvitationService
{
    /**
     * Generate a unique 40-character hex token.
     */
    public function generateToken(): string
    {
        return Str::random(40);
    }

    /**
     * Create a new feedback invitation from a completed referral.
     *
     * Resolves the correct SERVQUAL config: service-specific override first,
     * then agency default. Snapshots the form at creation time.
     *
     * @return array{invitation: FeedbackInvitation, token: string}
     */
    public function createInvitation(
        string $caseId,
        string $agencyId,
        string $referralId,
        ?string $clientEmail = null,
        ?string $serviceId = null,
    ): array {
        $token = $this->generateToken();

        // Resolve the correct SERVQUAL config
        $config = $this->resolveConfig($agencyId, $serviceId);

        $formSnapshot = $config?->questions ?? [];
        $service = $serviceId ? Service::where('id', $serviceId)
            ->where('agcy_id', $agencyId)
            ->where('is_deleted', false)
            ->first() : null;
        $serviceName = $service?->name ?? $config?->service_name ?? '';
        $snapshotSource = $config ? 'agency_active_form' : 'system_default';

        if (! $config) {
            Log::warning('No active feedback form found for agency', [
                'agency_id' => $agencyId,
                'service_id' => $serviceId,
            ]);
        }

        $invitation = FeedbackInvitation::create([
            'case_id' => $caseId,
            'agency_id' => $agencyId,
            'referral_id' => $referralId,
            'client_email' => $clientEmail,
            'token_prefix' => substr($token, 0, 10),
            'token_hash' => hash('sha256', $token),
            'service_id' => $serviceId,
            'service_name' => $serviceName,
            'snapshot_source' => $snapshotSource,
            'form_snapshot' => $formSnapshot,
            'rating_labels' => FeedbackInvitation::RATING_LABELS,
            'expires_at' => now()->addDays(30),
            'submitted_at' => null,
            'used_feedback_id' => null,
        ]);

        return [
            'invitation' => $invitation,
            'token' => $token,
        ];
    }

    /**
     * Resolve the SERVQUAL config for a given agency and optional service.
     *
     * Priority: service-specific override → agency default (service_id IS NULL).
     */
    private function resolveConfig(string $agencyId, ?string $serviceId): ?ServqualConfig
    {
        // 1. Look for service-specific override
        if ($serviceId) {
            $override = ServqualConfig::where('agency_id', $agencyId)
                ->where('service_id', $serviceId)
                ->where('is_active', true)
                ->first();

            if ($override) {
                return $override;
            }
        }

        // 2. Fall back to agency default (service_id IS NULL)
        return ServqualConfig::where('agency_id', $agencyId)
            ->whereNull('service_id')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Validate a raw feedback token and return the matching invitation.
     *
     * @param  string  $token  The full 40-char raw token from the email link.
     *
     * @throws \RuntimeException When token is invalid, expired, or already submitted.
     */
    public function validatePublicToken(string $token): FeedbackInvitation
    {
        $prefix = substr($token, 0, 10);
        $hash = hash('sha256', $token);

        $invitation = FeedbackInvitation::where('token_prefix', $prefix)
            ->where('token_hash', $hash)
            ->first();

        if (! $invitation) {
            throw new \RuntimeException('Invalid feedback link.');
        }

        if ($invitation->isExpired()) {
            throw new \RuntimeException('This feedback link has expired.');
        }

        if ($invitation->isSubmitted()) {
            throw new \RuntimeException('This feedback has already been submitted.');
        }

        return $invitation;
    }

    /**
     * Mark an invitation as submitted and link it to a feedback record.
     */
    public function markSubmitted(FeedbackInvitation $invitation, Feedback $feedback): void
    {
        $invitation->update([
            'submitted_at' => now(),
            'used_feedback_id' => $feedback->id,
        ]);
    }

    /**
     * Get an invitation by ID with loaded relationships.
     */
    public function findById(string $id): ?FeedbackInvitation
    {
        return FeedbackInvitation::with(['caseFile', 'agency', 'referral', 'feedback'])->find($id);
    }
}
