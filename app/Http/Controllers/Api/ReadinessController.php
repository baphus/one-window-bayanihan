<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Reports\PdfChartRenderer;
use App\Support\MailTransportHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Deep readiness probe for external monitoring.
 *
 * Distinct from the framework health route at /up, which answers 200 without
 * touching the database. That shallow check is correct for the container
 * orchestrator — a deep check wired to it turns a transient database blip into a
 * restart loop — but it is not sufficient for alerting: it reported this
 * application healthy while the queue worker and scheduler were crash-looping
 * and every authenticated page returned 502.
 *
 * Responds 200 when every check passes and 503 when any fails, so a monitor can
 * alert on status code alone.
 */
class ReadinessController extends Controller
{
    /**
     * Cache key the scheduler heartbeat is written to (see routes/console.php).
     */
    public const SCHEDULER_HEARTBEAT_KEY = 'monitoring:scheduler-heartbeat';

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizeProbe($request);

        $thresholds = config('monitoring.thresholds');

        $checks = [
            'database' => $this->checkDatabase(),
            'scheduler' => $this->checkScheduler((int) $thresholds['scheduler_stale_seconds']),
            'mail' => $this->checkMailTransport(),
            'image_rendering' => $this->checkImageRendering(),
        ];

        // Queue depth is only observable through these tables on the database
        // driver. On any other driver report "skipped" rather than inventing a
        // healthy result from a table that no longer reflects reality.
        if (config('queue.default') === 'database') {
            $checks['queue_backlog'] = $this->checkCount('jobs', (int) $thresholds['queue_backlog']);
            $checks['failed_jobs'] = $this->checkCount('failed_jobs', (int) $thresholds['failed_jobs']);
        } else {
            $checks['queue_backlog'] = ['status' => 'skipped', 'driver' => config('queue.default')];
            $checks['failed_jobs'] = ['status' => 'skipped', 'driver' => config('queue.default')];
        }

        $failed = array_keys(array_filter(
            $checks,
            fn (array $check): bool => $check['status'] === 'fail'
        ));

        return response()->json([
            'status' => $failed === [] ? 'ok' : 'degraded',
            'failing' => $failed,
            'checks' => $checks,
        ], $failed === [] ? 200 : 503);
    }

    /**
     * Fail closed: with no token configured the route does not exist, so an
     * unset secret cannot expose backlog counts to anonymous callers.
     */
    private function authorizeProbe(Request $request): void
    {
        $expected = (string) config('monitoring.readiness_token', '');

        if ($expected === '') {
            throw new NotFoundHttpException;
        }

        $presented = (string) $request->header('X-Monitoring-Token', '');

        if (! hash_equals($expected, $presented)) {
            throw new NotFoundHttpException;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            // The message can name the host and role, so it stays out of the
            // response body and goes to the log instead.
            report($e);

            return ['status' => 'fail', 'detail' => 'connection failed'];
        }
    }

    /**
     * Can the configured mailer actually be built?
     *
     * This probe reported the application ready while EVERY outbound mail was
     * failing: the Resend key was shipped under a name config did not read, so
     * Mail::to() — which resolves the transport eagerly — threw a TypeError and
     * returned 500 on intake OTP, MFA and password reset alike. /up cannot see
     * that, and until now neither could this.
     *
     * Constructing the transport is the same path Mail::to() takes and opens no
     * connection, so this stays a cheap check rather than a probe that sends.
     *
     * @return array<string, mixed>
     */
    private function checkMailTransport(): array
    {
        $mailer = (string) config('mail.default');

        // Shared with the container's boot preflight so the two gates cannot
        // disagree about what counts as deliverable.
        $problem = MailTransportHealth::problem($mailer);

        if ($problem !== null) {
            // Log once every 5 minutes, not once per poll. MailManager never
            // caches a failed resolve, so an uncontrolled log here would write
            // a stack trace on every monitor tick — flooding the log precisely
            // while someone is reading it to diagnose the mailer.
            if (Cache::add('monitoring:mail-fail-logged', true, 300)) {
                Log::error('Readiness: mail transport unusable', [
                    'mailer' => $mailer,
                    'reason' => $problem['reason'],
                ]);
            }

            // The reason can name a credential, so the response body stays
            // generic and the detail goes to the log.
            return ['status' => 'fail', 'mailer' => $mailer, 'detail' => 'transport unusable'];
        }

        // Not a failure — `log` is the deliberate pre-launch setting — but it
        // must never be mistaken for working delivery on a dashboard.
        if ($mailer === 'log') {
            return ['status' => 'ok', 'mailer' => $mailer, 'detail' => 'writes to the log; nothing is delivered'];
        }

        return ['status' => 'ok', 'mailer' => $mailer];
    }

    /**
     * Can this build draw the report charts?
     *
     * Same class of failure as the mail check above, and it shipped the same
     * way: the image was built with `docker-php-ext-install gd` but without
     * `docker-php-ext-configure gd --with-freetype`, so imagettftext() was
     * never defined and every Reports PDF export returned 500 from the first
     * chart label. /up answered 200 throughout, the test suite passed on hosts
     * where FreeType exists, and the gap was only found from a user's stack
     * trace nine days later.
     *
     * Reported as a failure rather than a warning: charts now degrade to a
     * bitmap font instead of fatalling, but a deployment in that state is
     * shipping visibly worse documents and someone should be told.
     *
     * @return array<string, mixed>
     */
    private function checkImageRendering(): array
    {
        if (! extension_loaded('gd')) {
            return ['status' => 'fail', 'detail' => 'gd extension missing'];
        }

        if (! PdfChartRenderer::hasTrueType()) {
            return ['status' => 'fail', 'detail' => 'gd built without freetype; chart labels degrade to bitmap'];
        }

        return ['status' => 'ok', 'freetype' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkScheduler(int $staleAfterSeconds): array
    {
        try {
            $heartbeat = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);
        } catch (\Throwable $e) {
            report($e);

            return ['status' => 'fail', 'detail' => 'heartbeat unreadable'];
        }

        if ($heartbeat === null) {
            return ['status' => 'fail', 'detail' => 'no heartbeat recorded'];
        }

        $ageSeconds = (int) Carbon::parse($heartbeat)->diffInSeconds(now(), absolute: true);

        return [
            'status' => $ageSeconds <= $staleAfterSeconds ? 'ok' : 'fail',
            'age_seconds' => $ageSeconds,
            'threshold_seconds' => $staleAfterSeconds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCount(string $table, int $threshold): array
    {
        try {
            $count = DB::table($table)->count();
        } catch (\Throwable $e) {
            report($e);

            return ['status' => 'fail', 'detail' => "could not read {$table}"];
        }

        return [
            'status' => $count <= $threshold ? 'ok' : 'fail',
            'count' => $count,
            'threshold' => $threshold,
        ];
    }
}
