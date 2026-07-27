<?php

use App\Http\Controllers\Api\ReadinessController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Scheduler liveness heartbeat. Everything below this line is invisible to the
// shallow /up health check: during the staging bring-up the scheduler could not
// open a database connection at all, so none of these jobs ran, while /up
// answered 200 throughout. GET /api/readyz alerts on a stale heartbeat.
// TTL is deliberately longer than the staleness threshold so the probe can tell
// "stopped ticking" apart from "key expired".
Schedule::call(function (): void {
    Cache::put(ReadinessController::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String(), now()->addHour());
})->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping();

Schedule::command('helpcenter:sync')->hourly()->withoutOverlapping();

Schedule::command('logs:cleanup')->dailyAt('03:00');

// Audit lifecycle: archive expired months to immutable bundles first, then
// prune (prune refuses rows not covered by a finalized bundle).
Schedule::command('audit:archive')->monthlyOn(1, '01:00')->withoutOverlapping();
Schedule::command('audit:prune --force')->monthlyOn(1, '02:30')->withoutOverlapping();
Schedule::command('audit:verify')->weeklyOn(1, '04:00')->withoutOverlapping();

Schedule::command('storage:cleanup-orphans')->daily();

// Permanently delete soft-deleted cases older than the retention window.
Schedule::command('cases:purge-trashed')->dailyAt('02:00')->withoutOverlapping();

// Prune old generated documents (completed > 30 days, failed > 7 days).
Schedule::command('documents:prune')->dailyAt('03:30')->withoutOverlapping();
