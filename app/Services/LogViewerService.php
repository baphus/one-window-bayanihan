<?php

namespace App\Services;

class LogViewerService
{
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
        rsort($dates);

        return $dates;
    }

    public function getLogs(int $perPage = 50, ?string $level = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null, bool $redact = true, int $page = 1): array
    {
        $files = glob(storage_path('logs/laravel-*.log'));
        rsort($files);

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
                if (! $m) {
                    return false;
                }
                $date = $m[1];
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
        foreach ($files as $file) {
            preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log/', $file, $m);
            $date = $m[1] ?? '';
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+):\s?(.*)/', $line, $m)) {
                    $entry = [
                        'timestamp' => $m[1],
                        'environment' => $m[2],
                        'level' => strtolower($m[3]),
                        'message' => $m[4],
                        'date' => $date,
                    ];

                    // Redact PII from log messages
                    if ($redact) {
                        $entry['message'] = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '***@***.***', $entry['message']);
                        $entry['message'] = preg_replace('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', '***.***.***.***', $entry['message']);
                    }

                    if ($level && strtolower($entry['level']) !== strtolower($level)) {
                        continue;
                    }

                    if ($search && stripos($entry['message'], $search) === false) {
                        continue;
                    }

                    $entries[] = $entry;
                }
            }
        }

        $total = count($entries);
        $offset = ($page - 1) * $perPage;
        $paginated = array_slice($entries, $offset, $perPage);

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
