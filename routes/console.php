<?php

use Illuminate\Support\Facades\Schedule;

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
