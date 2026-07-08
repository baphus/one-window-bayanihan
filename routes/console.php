<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('helpcenter:sync')->hourly()->withoutOverlapping();

Schedule::command('logs:cleanup')->dailyAt('03:00');

Schedule::command('audit:prune --force')->monthly()->withoutOverlapping();

Schedule::command('storage:cleanup-orphans')->daily();
