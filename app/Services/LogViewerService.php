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

    /**
     * Log source files for a request, newest first, filtered to the requested
     * date window. Shared by the paginated viewer and the streaming download
     * so both paths see the same files; includes the single laravel.log
     * fallback (LOG_STACK=single), which counts as today's date.
     *
     * @return array<int|string, string>
     */
    private function logSourceFiles(?string $dateFrom, ?string $dateTo): array
    {
        $files = glob(storage_path('logs/laravel-*.log')) ?: [];
        rsort($files);

        // Fallback to single laravel.log (LOG_STACK=single)
        if (empty($files) && file_exists(storage_path('logs/laravel.log'))) {
            $files = [storage_path('logs/laravel.log')];
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

        return $files;
    }

    /**
     * Apply the shared redaction to one log message (same patterns as the
     * paginated viewer and the streaming download).
     */
    private function redactMessage(string $message): string
    {
        $message = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '***@***.***', $message);
        $message = preg_replace('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', '***.***.***.***', $message);

        return $message;
    }

    /**
     * Start the line-by-line streaming pass over the source files. Returns the
     * generator consumed by streamLogs(); memory stays bounded to one
     * line per handle.
     *
     * @return \Generator<int, array{timestamp: string, environment: string, level: string, message: string, date: string}>
     */
    private function streamEntries(?string $level, ?string $search, ?string $dateFrom, ?string $dateTo, bool $redact): \Generator
    {
        $files = $this->logSourceFiles($dateFrom, $dateTo);
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
                    return;
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
                    $message = $this->redactMessage($message);
                }

                if ($search && stripos($message, $search) === false) {
                    continue;
                }

                yield [
                    'timestamp' => $m[1],
                    'environment' => $m[2],
                    'level' => strtolower($m[3]),
                    'message' => $message,
                    'date' => $date,
                ];
            }
        }
    }

    /**
     * Streaming variant of getLogs() for the download endpoint: yields every
     * matching entry with the SAME filters, redaction and MAX_SCANNED_LINES
     * cap as getLogs(), but never materialises the full result set — the
     * caller consumes one entry at a time.
     *
     * @return \Generator<int, array{timestamp: string, environment: string, level: string, message: string, date: string}>
     */
    public function streamLogs(?string $level = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null, bool $redact = true): \Generator
    {
        return $this->streamEntries($level, $search, $dateFrom, $dateTo, $redact);
    }

    public function getLogs(int $perPage = 50, ?string $level = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null, bool $redact = true, int $page = 1): array
    {
        $files = $this->logSourceFiles($dateFrom, $dateTo);

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
                    $message = $this->redactMessage($message);
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
