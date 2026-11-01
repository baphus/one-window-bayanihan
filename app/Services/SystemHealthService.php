<?php

namespace App\Services;

use App\Services\Reports\PdfChartRenderer;
use App\Support\MailTransportHealth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * System health for the admin Health page.
 *
 * Deliberately NOT a second readiness probe. The checks here are the same
 * question /api/readyz answers, but that endpoint is token-gated for an
 * external monitor and answers by HTTP status code only; an admin needs the
 * detail behind each answer, in-process, on a page.
 *
 * ReadinessController stays the authority for "is this deployment healthy".
 * This class exists so the page can show WHY. It adds no instrumentation:
 * every value comes from something already written — the scheduler heartbeat
 * in routes/console.php, the thresholds in config/monitoring.php, and
 * MailTransportHealth, the shared predicate both gates already use.
 */
class SystemHealthService
{
    /**
     * Cache key the scheduler heartbeat is written to (see routes/console.php).
     * Mirrors ReadinessController::SCHEDULER_HEARTBEAT_KEY.
     */
    public const SCHEDULER_HEARTBEAT_KEY = 'monitoring:scheduler-heartbeat';

    /**
     * Every check, in the order the page should show them.
     *
     * @return array<int, array{key: string, label: string, status: string, summary: string, detail: ?string}>
     */
    public function checks(): array
    {
        $thresholds = config('monitoring.thresholds');

        return [
            $this->databaseCheck(),
            $this->schedulerCheck((int) $thresholds['scheduler_stale_seconds']),
            $this->mailCheck(),
            $this->queueBacklogCheck((int) $thresholds['queue_backlog']),
            $this->failedJobsCheck((int) $thresholds['failed_jobs']),
            $this->imageRenderingCheck(),
        ];
    }

    /**
     * True when any check is failing. The page renders a single overall badge
     * from this rather than making the reader scan every row.
     */
    public function isHealthy(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{key: string, label: string, status: string, summary: string, detail: ?string}
     */
    private function databaseCheck(): array
    {
        try {
            DB::select('select 1');

            return $this->result('database', 'Database', 'ok', 'Connection succeeded');
        } catch (\Throwable $e) {
            // The message can name the host and role, so it is summarized here
            // and left to the normal exception handler to report.
            report($e);

            return $this->result('database', 'Database', 'fail', 'Connection failed');
        }
    }

    /**
     * Has the scheduler written its heartbeat recently?
     *
     * schedule:work ticks every minute, so a missing or stale heartbeat means
     * the scheduled retention and archive jobs are not running — which no
     * page-level symptom reports until data accumulates.
     *
     * @return array{key: string, label: string, status: string, summary: string, detail: ?string}
     */
    private function schedulerCheck(int $staleAfterSeconds): array
    {
        try {
            $heartbeat = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);
        } catch (\Throwable $e) {
            report($e);

            return $this->result('scheduler', 'Scheduler', 'fail', 'Heartbeat unreadable');
        }

        if ($heartbeat === null) {
            return $this->result(
                'scheduler',
                'Scheduler',
                'fail',
                'No heartbeat recorded',
                'The scheduler has not run since this cache key was cleared, or schedule:work is not running.'
            );
        }

        $age = (int) Carbon::parse($heartbeat)->diffInSeconds(now(), absolute: true);

        return $this->result(
            'scheduler',
            'Scheduler',
            $age <= $staleAfterSeconds ? 'ok' : 'fail',
            $this->humanDuration($age).' ago',
            "Threshold: {$staleAfterSeconds}s since the last tick."
        );
    }

    /**
     * Can the configured mailer be built?
     *
     * Uses the same predicate as the container preflight and /api/readyz, so
     * this page cannot disagree with either of them.
     *
     * @return array{key: string, label: string, status: string, summary: string, detail: ?string}
     */
    private function mailCheck(): array
    {
        $mailer = (string) config('mail.default');
        $problem = MailTransportHealth::problem($mailer);

        if ($problem !== null) {
            // The reason can name a credential, so the page shows a generic
            // message and the detail carries only the actionable hint.
            return $this->result('mail', 'Mail transport', 'fail', "The '{$mailer}' mailer cannot be used", $problem['hint']);
        }

        // Not a failure — `log` is the deliberate pre-launch setting — but it
        // must never read as working delivery on an admin page.
        if ($mailer === 'log') {
            return $this->result(
                'mail',
                'Mail transport',
                'warn',
                'Mail is written to the log; nothing is delivered',
                'Set MAIL_MAILER to a real driver to send email.'
            );
        }

        return $this->result('mail', 'Mail transport', 'ok', "Driver: {$mailer}");
    }

    /**
     * Queue depth is only observable through these tables on the database
     * driver. On any other driver report "unknown" rather than inventing a
     * healthy result from a table that does not reflect reality.
     *
     * @return array{key: string, label: string, status: string, summary: string, detail: ?string}
     */
    private function queueBacklogCheck(int $threshold): array
    {
        if (config('queue.default') !== 'database') {
            return $this->result(
                'queue_backlog',
                'Queue backlog',
                'unknown',
                'Not observable on the '.config('queue.default').' driver',
                'Pending job counts require the database queue driver.'
            );
        }

        try {
            $count = DB::table('jobs')->count();
        } catch (\Throwable $e) {
            report($e);

            return $this->result('queue_backlog', 'Queue backlog', 'fail', 'Could not read the jobs table');
        }

        return $this->result(
            'queue_backlog',
            'Queue backlog',
            $count <= $threshold ? 'ok' : 'fail',
            $count.' pending '.($count === 1 ? 'job' : 'jobs'),
            "Alert threshold: {$threshold}."
        );
    }

    /**
     * @return array{key: string, label: string, status: string, summary: string, detail: ?string}
     */
    private function failedJobsCheck(int $threshold): array
    {
        if (config('queue.default') !== 'database') {
            return $this->result(
                'failed_jobs',
                'Failed jobs',
                'unknown',
                'Not observable on the '.config('queue.default').' driver'
            );
        }

        try {
            $count = DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            report($e);

            return $this->result('failed_jobs', 'Failed jobs', 'fail', 'Could not read the failed_jobs table');
        }

        return $this->result(
            'failed_jobs',
            'Failed jobs',
            $count <= $threshold ? 'ok' : 'fail',
            $count.' '.($count === 1 ? 'failure' : 'failures').' recorded',
            "Alert threshold: {$threshold}."
        );
    }

    /**
     * Can this build draw report charts?
     *
     * Same class of failure /api/readyz reports: a build without FreeType
     * degrades chart labels to a bitmap font rather than fatalling.
     *
     * @return array{key: string, label: string, status: string, summary: string, detail: ?string}
     */
    private function imageRenderingCheck(): array
    {
        if (! extension_loaded('gd')) {
            return $this->result('image_rendering', 'Report rendering', 'fail', 'The gd extension is missing');
        }

        if (! PdfChartRenderer::hasTrueType()) {
            return $this->result(
                'image_rendering',
                'Report rendering',
                'warn',
                'gd has no FreeType; chart labels fall back to a bitmap font',
                'Rebuild the image with gd configured --with-freetype.'
            );
        }

        return $this->result('image_rendering', 'Report rendering', 'ok', 'TrueType rendering available');
    }

    /**
     * @param  string  $key  Stable identifier for the check.
     * @param  string  $label  Human label.
     * @param  string  $status  ok | warn | fail | unknown.
     * @param  string  $summary  One line, always safe to display.
     * @param  string|null  $detail  Optional second line, safe to display.
     * @return array{key: string, label: string, status: string, summary: string, detail: ?string}
     */
    private function result(string $key, string $label, string $status, string $summary, ?string $detail = null): array
    {
        return compact('key', 'label', 'status', 'summary', 'detail');
    }

    private function humanDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' '.($seconds === 1 ? 'second' : 'seconds');
        }

        if ($seconds < 3600) {
            $minutes = intdiv($seconds, 60);

            return $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        }

        $hours = intdiv($seconds, 3600);

        return $hours.' '.($hours === 1 ? 'hour' : 'hours');
    }
}
