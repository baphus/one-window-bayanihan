<?php

namespace App\Services\Referral;

class ReferralStatusMachine
{
    /**
     * Allowed referral status transitions, keyed by the current status.
     *
     * Self-transitions are allowed so that duplicate requests become
     * idempotent no-ops instead of re-firing notifications.
     *
     * @var array<string, array<int, string>>
     */
    private const STATUS_TRANSITIONS = [
        'PENDING' => ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'REJECTED'],
        'PROCESSING' => ['PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'],
        'FOR_COMPLIANCE' => ['FOR_COMPLIANCE', 'PROCESSING', 'COMPLETED', 'REJECTED'],
        'COMPLETED' => ['COMPLETED'],
        'REJECTED' => ['REJECTED'],
    ];

    /**
     * Reject any status change that is not part of the referral workflow.
     *
     * @throws \InvalidArgumentException
     */
    public function assertAllowedTransition(string $from, string $to): void
    {
        $allowed = self::STATUS_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Cannot change referral status from {$from} to {$to}."
            );
        }
    }
}
