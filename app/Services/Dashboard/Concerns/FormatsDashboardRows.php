<?php

namespace App\Services\Dashboard\Concerns;

use Carbon\Carbon;

trait FormatsDashboardRows
{
    /**
     * Generate a safe relative time string that never shows "from now".
     * Handles timezone discrepancies by clamping future timestamps to "Just now".
     */
    private function safeRelativeTime(?Carbon $timestamp): string
    {
        if (! $timestamp) {
            return 'N/A';
        }

        $now = Carbon::now();

        if ($timestamp->isAfter($now)) {
            return 'Just now';
        }

        return $timestamp->diffForHumans();
    }

    private function formatChangeSummary(array $changes): string
    {
        if (empty($changes)) {
            return '';
        }

        $first = $changes[0];
        $summary = $first['fieldLabel'].': ';

        // The audit response carries after-only changes (no `old` key), so a
        // missing old value simply starts the summary at the new value.
        $old = $first['old'] ?? null;

        if ($old !== null && $old !== 'not set') {
            $summary .= $old.' → ';
        }

        $summary .= $first['new'] ?? '';

        if (count($changes) > 1) {
            $summary .= ' (+'.(count($changes) - 1).' more)';
        }

        return $summary;
    }

    private function queueItem(string $key, string $label, int $count, string $note, string $tone, string $icon, string $href): array
    {
        return compact('key', 'label', 'count', 'note', 'tone', 'icon', 'href');
    }

    private function ageInDays($timestamp): int
    {
        if (! $timestamp) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($timestamp, false) * -1);
    }

    private function daysBetween($start, $end): ?int
    {
        if (! $start || ! $end) {
            return null;
        }

        return max(0, (int) $start->startOfDay()->diffInDays($end->startOfDay(), false));
    }

    private function clientName($client): string
    {
        if (! $client) {
            return 'N/A';
        }

        return trim(($client->first_name ?? '').' '.($client->last_name ?? '')) ?: 'N/A';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'PENDING' => 'Pending',
            'PROCESSING' => 'Processing',
            'FOR_COMPLIANCE' => 'For compliance',
            'COMPLETED' => 'Completed',
            'REJECTED' => 'Rejected',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'PENDING' => 'amber',
            'PROCESSING' => 'blue',
            'FOR_COMPLIANCE' => 'orange',
            'COMPLETED' => 'emerald',
            'REJECTED' => 'rose',
            default => 'slate',
        };
    }

    private function buildStatusDistributionFromCounts(array $statusCounts, int $total): array
    {
        $total = max($total, 1);

        return collect($statusCounts)
            ->map(fn (int $count, string $status) => [
                'status' => $status,
                'label' => $this->statusLabel($status),
                'count' => $count,
                'percent' => (int) round(($count / $total) * 100),
                'tone' => $this->statusTone($status),
            ])
            ->filter(fn (array $item) => $item['count'] > 0)
            ->values()
            ->toArray();
    }
}
