<?php

namespace App\Services;

use SplFileObject;

class LogViewerService
{
    /**
     * Upper bound on raw lines scanned per request so a huge log
     * directory cannot exhaust memory/time. ponytail: raise only
     * alongside pagination of the download endpoint.
     */
    private const MAX_SCANNED_LINES = 200000;

    public function getAvailableDates(): array
    {
        $files = glob(storage_path('logs/laravel-*.log'));
        $dates = [];
        foreach ($files as $file) {
            preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log/', $file, $m);
            if ($m) {
                $dates[] = $m[1];
            }
        }

        // Fallback to single laravel.log (LOG_STACK=single)
        if (empty($dates) && file_exists(storage_path('logs/laravel.log'))) {
            return [date('Y-m-d')];
        }

        rsort($dates);

        return $dates;
    }

    public function getLogs(int $perPage = 50, ?string $level = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null, bool $redact = true, int $page = 1): array
    {
        $files = glob(storage_path('logs/laravel-*.log'));
        rsort($files);

        // Fallback to single laravel.log (LOG_STACK=single)
        if (empty($files)) {
            $singleLog = storage_path('logs/laravel.log');
            if (file_exists($singleLog)) {
                $files = [$singleLog];
            }
        }

        if (empty($files)) {
            return [
                'entries' => [],
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => 1,
                'last_page' => 1,
                'levels' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
                'source_available' => false,
                'unavailable_reason' => 'Log source unavailable.',
                'warning' => '⚠️ Logs may contain sensitive data. Handle with care.',
            ];
        }

        if ($dateFrom || $dateTo) {
            $files = array_filter($files, function ($f) use ($dateFrom, $dateTo) {
                preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log/', $f, $m);
                // Single laravel.log has no date — treat as today
                $date = $m[1] ?? date('Y-m-d');
                if ($dateFrom && $date < $dateFrom) {
                    return false;
                }
                if ($dateTo && $date > $dateTo) {
                    return false;
                }

                return true;
            });
        }

        $entries = [];
        $total = 0;
        $offset = ($page - 1) * $perPage;
        $end = $offset + $perPage;
        $scanned = 0;
        $levelFilter = $level !== null ? strtolower($level) : null;
        foreach ($files as $file) {
            preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log/', $file, $m);
            $date = $m[1] ?? '';
            try {
                $handle = new SplFileObject($file, 'r');
            } catch (\RuntimeException) {
                continue;
            }

            while (! $handle->eof()) {
                $line = trim((string) $handle->fgets());
                if ($line === '') {
                    continue;
                }
                if (++$scanned > self::MAX_SCANNED_LINES) {
                    break 2;
                }
                if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+):\s?(.*)/', $line, $m)) {
                    continue;
                }
                if ($levelFilter !== null && strtolower($m[3]) !== $levelFilter) {
                    continue;
                }

                $message = $m[4];

                // Redact PII from log messages
                if ($redact) {
                    $message = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '***@***.***', $message);
                    $message = preg_replace('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', '***.***.***.***', $message);
                }

                if ($search && stripos($message, $search) === false) {
                    continue;
                }

                if ($total >= $offset && $total < $end) {
                    $entries[] = [
                        'timestamp' => $m[1],
                        'environment' => $m[2],
                        'level' => strtolower($m[3]),
                        'message' => $message,
                        'date' => $date,
                    ];
                }
                $total++;
            }
        }

        $paginated = $entries;

        return [
            'entries' => $paginated,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'levels' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
            'source_available' => true,
            'warning' => '⚠️ Logs may contain sensitive data. Handle with care.',
        ];
    }
}
