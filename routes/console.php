<?php

use App\Http\Controllers\Api\ReadinessController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Sentry\Severity;
use Sentry\State\Scope;

use function Sentry\captureMessage;
use function Sentry\configureScope;

// Scheduler liveness heartbeat. Everything below this line is invisible to the
// shallow /up health check: during the staging bring-up the scheduler could not
// open a database connection at all, so none of these jobs ran, while /up
// answered 200 throughout. GET /api/readyz alerts on a stale heartbeat.
// TTL is deliberately longer than the staleness threshold so the probe can tell
// "stopped ticking" apart from "key expired".
Schedule::call(function (): void {
    Cache::put(ReadinessController::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String(), now()->addHour());
})->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping();

// Report a scheduled command that ended in failure (non-zero exit or thrown
// exception) to Sentry. The scheduler fires `onFailure` for both shapes; the
// exception is also reported by the exception handler, but an explicit message
// guarantees a signal for commands that return non-zero without throwing.
$reportFailure = function (array $context = []) {
    return function () use ($context) {
        configureScope(function (Scope $scope) use ($context): void {
            foreach ($context as $key => $value) {
                $scope->setTag((string) $key, (string) $value);
            }

            $scope->setTag('origin', 'scheduler');
            $scope->setTag('queue_connection', (string) config('queue.default'));
        });

        captureMessage(
            'Scheduled command failed: '.($context['command'] ?? 'unknown'),
            Severity::error(),
        );
    };
};

Schedule::command('helpcenter:sync')->hourly()->withoutOverlapping()
    ->onFailure($reportFailure(['command' => 'helpcenter:sync']));

Schedule::command('logs:cleanup')->dailyAt('03:00')
    ->onFailure($reportFailure(['command' => 'logs:cleanup']));

// Audit lifecycle: archive expired months to immutable bundles first, then
// prune (prune refuses rows not covered by a finalized bundle).
Schedule::command('audit:archive')->monthlyOn(1, '01:00')->withoutOverlapping()
    ->onFailure($reportFailure(['command' => 'audit:archive']));
Schedule::command('audit:prune --force')->monthlyOn(1, '02:30')->withoutOverlapping()
    ->onFailure($reportFailure(['command' => 'audit:prune']));
Schedule::command('audit:verify')->weeklyOn(1, '04:00')->withoutOverlapping()
    ->onFailure($reportFailure(['command' => 'audit:verify']));

Schedule::command('storage:cleanup-orphans')->daily()
    ->onFailure($reportFailure(['command' => 'storage:cleanup-orphans']));

// Permanently delete soft-deleted cases older than the retention window.
Schedule::command('cases:purge-trashed')->dailyAt('02:00')->withoutOverlapping()
    ->onFailure($reportFailure(['command' => 'cases:purge-trashed']));

// Prune old generated documents (completed > 30 days, failed > 7 days).
Schedule::command('documents:prune')->dailyAt('03:30')->withoutOverlapping()
    ->onFailure($reportFailure(['command' => 'documents:prune']));
