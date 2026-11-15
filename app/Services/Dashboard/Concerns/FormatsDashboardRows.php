<?php

namespace App\Services\Dashboard\Concerns;

use App\Models\AuditLog;
use App\Services\AuditLogFormatter;
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

    private function queueItem(string $key, string $label, int $count, string $note, string $tone, string $icon, string $href): array
    {
        return compact('key', 'label', 'count', 'note', 'tone', 'icon', 'href');
    }

    /**
     * Format one audit row for a dashboard activity feed. A row the formatter
     * cannot handle degrades to a neutral sentence instead of failing the
     * whole widget.
     */
    private function auditDisplay(AuditLog $log, AuditLogFormatter $formatter): array
    {
        try {
            return $formatter->formatForAuditResponse($log);
        } catch (\Throwable $e) {
            report($e);

            return [
                'id' => (string) $log->getKey(),
                'message' => 'Activity recorded',
                'changes' => [],
                'action' => $log->action,
                'module' => 'other',
                'actor' => $log->user?->name ?? 'System',
                'timestamp' => $log->timestamp?->toISOString(),
                'hasChanges' => false,
            ];
        }
    }

    /**
     * One activity row in the AuditLogRow contract (resources/js/lib/audit.jsx).
     * Every dashboard feed goes through here so the shape cannot drift by role.
     */
    private function activityRow(AuditLog $log, array $display): array
    {
        return [
            'id' => (string) $log->getKey(),
            'action' => $display['action'] ?? null,
            'module' => $display['module'] ?? null,
            'formatted_module' => $display['formatted_module'] ?? null,
            'message' => $display['message'] ?? '',
            'changes' => $display['changes'] ?? [],
            'actor' => $display['actor'] ?? 'System',
            'timestamp' => $display['timestamp'] ?? null,
            'hasChanges' => (bool) ($display['hasChanges'] ?? false),
        ];
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
