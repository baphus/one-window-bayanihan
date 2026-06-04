<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('helpcenter:sync')->hourly()->withoutOverlapping();

Schedule::command('system:health-check')->everyFiveMinutes();

Schedule::command('cloudinary:check-usage')->dailyAt('02:00');

Schedule::command('insights:check-alerts')->everyFiveMinutes();

Schedule::command('insights:refresh-views')->everyFifteenMinutes();

Schedule::command('logs:cleanup')->dailyAt('03:00');
